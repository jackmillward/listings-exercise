<?php

namespace App\Search;

use App\Models\Branch;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class ListingService
{
    /**
     * Live listings matching a set of criteria, newest first.
     *
     * @return LengthAwarePaginator<int, Listing>
     */
    public function liveListingsMatching(SearchCriteria $criteria, int $perPage): LengthAwarePaginator
    {
        // `id` is a tiebreaker: without it, listings sharing a `listed_at` can be
        // ordered differently between page requests, which duplicates or skips
        // rows as you page through.
        return Listing::query()->live()
            ->matching($criteria)
            ->latest('listed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Where the save-search button points, built from the same criteria as the
     * query so the search being viewed and the one about to be saved agree.
     */
    public function saveSearchUrl(SearchCriteria $criteria): string
    {
        return URL::route('saved-searches.create', $criteria->toQueryArray(), absolute: false);
    }

    /**
     * The branches offered by the area filter, which the saved-search form
     * shares so both offer the same regions.
     *
     * @return Collection<int, Branch>
     */
    public function branches(): Collection
    {
        return Branch::query()->orderBy('name')->get();
    }
}
