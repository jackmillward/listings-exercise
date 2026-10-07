<?php

namespace Tests\Feature;

use App\Http\Middleware\ActAsDemoUser;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The ActAsDemoUser stub replaces the request's user resolver with the first
     * seeded user, so actingAs() alone is ignored and the stub has to come off
     * for a test to prove anything about a particular user.
     */
    private function asUser(User $user): self
    {
        return $this->withoutMiddleware(ActAsDemoUser::class)->actingAs($user);
    }

    /**
     * Every user-scoped page resolves its user through one shared guard, so an
     * unresolvable request is refused the same way whichever page asked.
     */
    #[DataProvider('userScopedPageProvider')]
    public function test_a_user_scoped_page_refuses_a_request_with_no_user(string $url): void
    {
        $this->get($url)->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function userScopedPageProvider(): array
    {
        return [
            'saved searches' => ['/saved-searches'],
            'alerts' => ['/saved-searches/alerts'],
        ];
    }

    public function test_index_lists_only_the_users_saved_searches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        SavedSearch::factory()->for($user)->count(2)->create();
        SavedSearch::factory()->for($other)->count(3)->create();

        $this->asUser($user)
            ->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 2)
            );
    }

    public function test_create_prefills_the_form_from_the_query_string(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->get('/saved-searches/create?max_price=300000&region=Manchester')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Create')
                ->where('criteria.max_price', '300000')
                ->where('criteria.region', 'Manchester')
            );
    }

    /**
     * The create page is reachable on its own, so it has to render before the
     * user has filled anything in — an empty search is rejected on save, not on
     * opening the form.
     */
    public function test_create_renders_without_any_criteria(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->get('/saved-searches/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Create')
            );
    }

    /**
     * The create page links from the listings page with whatever the user was
     * looking at, so the two have to describe the same search.
     */
    public function test_the_listings_page_links_to_the_create_page_with_the_current_filters(): void
    {
        $this->get('/?max_price=300000&min_bedrooms=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('saveSearchUrl', '/saved-searches/create?min_bedrooms=2&max_price=300000')
            );
    }

    public function test_an_unfiltered_listings_page_links_to_a_bare_create_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('saveSearchUrl', '/saved-searches/create')
            );
    }

    /**
     * The criteria bounds are enforced on the create page too, so the form is
     * seeded from a search the listings page would have refused to run.
     */
    public function test_create_rejects_out_of_range_criteria(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->get('/saved-searches/create?min_bedrooms=99')
            ->assertSessionHasErrors('min_bedrooms');
    }

    /**
     * The duplicate and cap rules judge saving, not rendering the form: they
     * must not block the page the user is about to submit from.
     */
    public function test_create_does_not_apply_the_duplicate_rule(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create([
            'max_price' => 300000,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ]);

        $this->asUser($user)
            ->get('/saved-searches/create?max_price=300000')
            ->assertOk();
    }

    public function test_store_persists_the_criteria(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', [
                'name' => 'Green belt',
                'max_price' => '300000',
                'min_bedrooms' => '3',
                'region' => 'Manchester',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $user->id,
            'name' => 'Green belt',
            'max_price' => 300000,
            'min_bedrooms' => 3,
            'region' => 'Manchester',
        ]);
    }

    /**
     * Saving from the create page lands on the manage page, not back on the
     * form the user just submitted.
     */
    public function test_store_redirects_to_the_manage_page(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->from('/saved-searches/create?max_price=300000')
            ->post('/saved-searches', ['max_price' => '300000'])
            ->assertRedirect('/saved-searches');
    }

    public function test_store_accepts_a_search_without_a_name(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['max_price' => '300000'])
            ->assertRedirect();

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $user->id,
            'name' => null,
            'max_price' => 300000,
        ]);
    }

    public function test_store_rejects_a_search_with_no_criteria(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['name' => 'Everything'])
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    /**
     * A maximum of zero is kept as a real constraint, so the saved search
     * matches nothing. Reading it as unconstrained instead would have turned
     * this into a search that alerts on every listing the user ever sees.
     */
    public function test_a_saved_search_with_a_zero_max_price_matches_no_listing(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['max_price' => '0'])
            ->assertRedirect();

        $listing = Listing::factory()->draft()->create(['price' => 250_000]);
        $listing->update(['status' => 'live', 'listed_at' => now()]);

        Notification::assertNothingSent();
    }

    public function test_store_accepts_a_zero_minimum_bedrooms_with_another_criterion(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['min_bedrooms' => '0', 'region' => 'Leeds'])
            ->assertRedirect();

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $user->id,
            'min_bedrooms' => 0,
            'region' => 'Leeds',
        ]);
    }

    public function test_store_rejects_identical_criteria_from_the_same_user(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create([
            'max_price' => 300000,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ]);

        $this->asUser($user)
            ->post('/saved-searches', ['max_price' => '300000'])
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 1);
    }

    /**
     * The name is not part of a search's identity, so labelling an existing
     * search differently does not make it a different search.
     */
    public function test_store_rejects_identical_criteria_regardless_of_name(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->named('Original')->create([
            'max_price' => 300000,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ]);

        $this->asUser($user)
            ->post('/saved-searches', ['name' => 'Different name', 'max_price' => '300000'])
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 1);
    }

    /**
     * Two different users can each save the same search — the duplicate is
     * about one person being alerted twice, not about the criteria existing.
     */
    public function test_store_allows_the_same_criteria_for_a_different_user(): void
    {
        SavedSearch::factory()->create([
            'max_price' => 300000,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ]);
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['max_price' => '300000'])
            ->assertRedirect();

        $this->assertDatabaseCount('saved_searches', 2);
    }

    public function test_store_rejects_a_search_beyond_the_cap(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->count(config('search.saved_searches.max_per_user'))->create();

        $this->asUser($user)
            ->post('/saved-searches', ['region' => 'Bristol'])
            ->assertSessionHasErrors('criteria');

        $this->assertSame(config('search.saved_searches.max_per_user'), $user->savedSearches()->count());
    }

    public function test_store_rejects_an_out_of_range_criterion(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['min_bedrooms' => config('search.saved_searches.criteria.min_bedrooms.max') + 1])
            ->assertSessionHasErrors('min_bedrooms');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_store_rejects_an_unknown_property_type(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->post('/saved-searches', ['property_type' => 'castle'])
            ->assertSessionHasErrors('property_type');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_destroy_deletes_the_saved_search(): void
    {
        $user = User::factory()->create();
        $savedSearch = SavedSearch::factory()->for($user)->create();

        $this->asUser($user)
            ->delete("/saved-searches/{$savedSearch->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_searches', ['id' => $savedSearch->id]);
    }

    public function test_destroy_does_not_expose_another_users_saved_search(): void
    {
        $savedSearch = SavedSearch::factory()->create();
        $user = User::factory()->create();

        $this->asUser($user)
            ->delete("/saved-searches/{$savedSearch->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('saved_searches', ['id' => $savedSearch->id]);
    }

    public function test_index_exposes_each_saved_searchs_criteria(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create([
            'name' => 'Green belt',
            'max_price' => 300000,
            'min_bedrooms' => 3,
            'property_type' => null,
            'region' => 'Manchester',
        ]);

        $this->asUser($user)
            ->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('savedSearches.0.name', 'Green belt')
                ->where('savedSearches.0.criteria', [
                    'min_bedrooms' => '3',
                    'max_price' => '300000',
                    'region' => 'Manchester',
                ])
            );
    }
}
