<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Search\SavedSearchService;

class ListingObserver
{
    public function __construct(private readonly SavedSearchService $savedSearches) {}

    /**
     * Fires only on the transition into live, never on creation: a listing
     * created already live is seed or back-office data, and alerting on it
     * would hand every user a backlog on day one. This is also what makes "no
     * backfill" fall out of the design rather than needing separate enforcement.
     */
    public function updated(Listing $listing): void
    {
        if (! $listing->wasChanged('status') || $listing->status !== ListingStatus::Live) {
            return;
        }

        $this->savedSearches->alertMatchingUsers($listing);
    }
}
