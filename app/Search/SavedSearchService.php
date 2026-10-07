<?php

namespace App\Search;

use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\ListingMatchedSavedSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reading and writing a user's saved searches, and alerting the owners when a
 * listing matches one.
 */
class SavedSearchService
{
    /**
     * @return Collection<int, SavedSearch>
     */
    public function forUser(User $user): Collection
    {
        return $user->savedSearches()
            ->latest('id')
            ->get();
    }

    /**
     * Persist a search for a user. The criteria arrive already validated, so
     * this is the shape the model stores.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function saveFor(User $user, ?string $name, array $criteria): SavedSearch
    {
        return $user->savedSearches()->create([
            'name' => $name,
            ...$criteria,
        ]);
    }

    /**
     * Delete a search the user owns. Binding is by id, so ownership is settled
     * here; a search belonging to somebody else is refused rather than deleted,
     * and reports as missing so the id cannot be probed for existence.
     */
    public function deleteFor(?User $user, SavedSearch $savedSearch): bool
    {
        if ($user === null || $savedSearch->user_id !== $user->getAuthIdentifier()) {
            return false;
        }

        return $savedSearch->delete();
    }

    /**
     * The distinct users who have at least one saved search the listing matches.
     *
     * Matching is delegated to {@see Listing::scopeMatching()} one saved search
     * at a time rather than inverting the criteria into a single query over
     * `saved_searches`. The inverted form is one fast query, but it is a second
     * independent encoding of "a match" — every criterion added later would
     * need adding in two places, and a miss would be silent.
     *
     * @return Collection<int, User>
     */
    public function matchingUsersFor(Listing $listing): Collection
    {
        $listing->loadMissing('branch');

        return SavedSearch::query()
            ->get()
            ->filter(fn (SavedSearch $savedSearch): bool => $this->listingMatches($listing, $savedSearch))
            ->pluck('user_id')
            ->unique()
            ->map(fn (int $userId): User => User::query()->findOrFail($userId))
            ->values();
    }

    /**
     * Alert the distinct matching users, one alert each.
     *
     * The claim and the alert are written in one transaction, so a failure
     * part-way through rolls the claim back and leaves the pair still eligible
     * for a later attempt. The claim is what makes the "once" real: the unique
     * index rejects a second one, whether it comes from a listing returning to
     * the market or two requests racing.
     */
    public function alertMatchingUsers(Listing $listing): void
    {
        foreach ($this->matchingUsersFor($listing) as $user) {
            DB::transaction(function () use ($user, $listing): void {
                $claimed = ListingAlert::query()->insertOrIgnore([
                    'user_id' => $user->getKey(),
                    'listing_id' => $listing->getKey(),
                ]);

                if ($claimed === 0) {
                    return;
                }

                $user->notify(new ListingMatchedSavedSearch($listing));
            });
        }
    }

    private function listingMatches(Listing $listing, SavedSearch $savedSearch): bool
    {
        return Listing::query()
            ->matching($savedSearch->criteria())
            ->whereKey($listing->getKey())
            ->exists();
    }
}
