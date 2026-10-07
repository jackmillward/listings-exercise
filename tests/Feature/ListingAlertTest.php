<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\ListingMatchedSavedSearch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class ListingAlertTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The factory fills every criterion, so a test that means "a search on price
     * alone" has to blank the rest explicitly — otherwise a random bedroom
     * minimum or region stops it matching, and the test fails for the wrong
     * reason.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function savedSearch(User $user, array $criteria): SavedSearch
    {
        return SavedSearch::factory()->for($user)->create([
            'max_price' => null,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
            ...$criteria,
        ]);
    }

    public function test_going_live_alerts_a_user_whose_saved_search_matches(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertSentTo($user, ListingMatchedSavedSearch::class);
    }

    public function test_going_live_does_not_alert_a_user_whose_saved_search_does_not_match(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 500_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertNothingSent();
    }

    /**
     * An unconstrained criterion is "no opinion", so a search on price alone
     * still matches a listing whose region and type were never mentioned.
     */
    public function test_a_null_criterion_does_not_exclude_a_listing(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create([
            'price' => 200_000,
            'bedrooms' => 5,
        ]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertSentTo($user, ListingMatchedSavedSearch::class);
    }

    /**
     * One property, one alert: matching two of a user's saved searches must not
     * tell them twice about the same house.
     */
    public function test_two_matching_saved_searches_produce_one_alert(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertSentToTimes($user, ListingMatchedSavedSearch::class, 1);
    }

    public function test_only_the_user_whose_search_matched_is_alerted(): void
    {
        Notification::fake();

        $matched = User::factory()->create();
        $unmatched = User::factory()->create();

        $this->savedSearch($matched, ['max_price' => 300000]);
        $this->savedSearch($unmatched, ['max_price' => 100_000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertSentTo($matched, ListingMatchedSavedSearch::class);
        Notification::assertNotSentTo($unmatched, ListingMatchedSavedSearch::class);
    }

    /**
     * No backfill: a listing already live before the feature existed must not
     * alert anyone, however well it matches.
     */
    public function test_a_listing_created_as_live_sends_no_alerts(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        Listing::factory()->live()->create(['price' => 250_000]);

        Notification::assertNothingSent();
    }

    public function test_an_unrelated_update_sends_no_alerts(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->live()->create(['price' => 250_000]);
        $listing->update(['price' => 260_000]);

        Notification::assertNothingSent();
    }

    /**
     * Going live is the only alert-worthy transition; going back to draft is not
     * a new property.
     */
    public function test_leaving_live_sends_no_alerts(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->live()->create(['price' => 250_000]);
        $listing->update(['status' => 'draft']);

        Notification::assertNothingSent();
    }

    /**
     * A listing returning to the market after an offer falls through is not a
     * new property: the user already has the alert. The unique index on the
     * claim is what makes this hold, since a check-then-send would not.
     */
    public function test_a_listing_returning_to_live_does_not_alert_again(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);
        $listing->update(['status' => 'under_offer']);
        $listing->update(['status' => 'live']);

        $this->assertSame(1, $user->notifications()->count());
    }

    /**
     * The claim is what makes the duplicate guarantee hold, so it must be
     * recorded rather than implied.
     */
    public function test_an_alert_claims_the_pairing_of_user_and_listing(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $this->assertDatabaseHas('listing_alerts', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_the_database_rejects_a_second_claim_for_the_same_user_and_listing(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();

        ListingAlert::query()->create(['user_id' => $user->id, 'listing_id' => $listing->id]);

        $this->expectException(QueryException::class);

        ListingAlert::query()->create(['user_id' => $user->id, 'listing_id' => $listing->id]);
    }

    /**
     * A failed alert must not consume the claim, or the listing could never be
     * announced to that user again.
     */
    public function test_a_failed_alert_does_not_consume_the_claim(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);

        // Notifiable's database channel writes notifications; breaking the claim
        // write is what makes the surrounding transaction roll back.
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "listing_alerts"')) {
                throw new RuntimeException('listing_alerts write failed');
            }
        });

        try {
            $listing->update(['status' => 'live', 'listed_at' => now()]);
        } catch (RuntimeException) {
            // Expected: the failure is the thing under test.
        }

        DB::flushQueryLog();

        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_the_alert_carries_the_listing_it_announced(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $notification = $user->notifications()->sole();

        $this->assertSame(ListingMatchedSavedSearch::class, $notification->type);
        $this->assertSame($listing->id, $notification->data['listing_id']);
        $this->assertSame($listing->reference, $notification->data['reference']);
        $this->assertNull($notification->read_at);
    }

    public function test_a_region_search_only_matches_that_region(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->savedSearch($user, ['region' => 'Manchester']);

        $listing = Listing::factory()->draft()->for(Branch::factory()->create(['region' => 'Leeds']))->create();
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertNothingSent();
    }

    /**
     * Deleting a saved search must not take its alerts with it: the notifications
     * table has no foreign key to saved_searches, so history survives.
     */
    public function test_deleting_a_saved_search_keeps_its_alerts(): void
    {
        $user = User::factory()->create();
        $savedSearch = $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $this->assertSame(1, $user->notifications()->count());

        $savedSearch->delete();

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_the_alerts_page_lists_the_users_alerts(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $this->get('/saved-searches/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts', 1)
                ->where('alerts.0.listing_id', $listing->id)
                ->where('alerts.0.is_read', false)
            );
    }

    /**
     * An alert for a listing that has since gone is still shown, but must not
     * link to a page that would 404.
     */
    public function test_an_alert_for_a_non_live_listing_is_not_linked(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);
        $listing->update(['status' => 'sold']);

        $this->get('/saved-searches/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.0.is_live', false)
            );
    }

    /**
     * A deleted listing leaves a dangling id in the payload, so the page has to
     * render from the snapshot rather than 500.
     */
    public function test_an_alert_survives_its_listing_being_deleted(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);
        $listing->forceDelete();

        $this->get('/saved-searches/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts', 1)
                ->where('alerts.0.is_live', false)
                ->where('alerts.0.address_line_1', $listing->address_line_1)
            );
    }

    public function test_marking_all_alerts_read(): void
    {
        $user = User::factory()->create();
        $this->savedSearch($user, ['max_price' => 300000]);

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        $this->post('/saved-searches/alerts/read')->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
