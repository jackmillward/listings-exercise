<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Http\Requests\ListingIndexRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Search\ListingService;
use App\Search\SearchCriteria;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $listings) {}

    /**
     * List live listings, with optional filters.
     */
    public function index(ListingIndexRequest $request): Response
    {
        $criteria = SearchCriteria::fromArray($request->only(
            'property_type',
            'max_price',
            'min_bedrooms',
            'region',
        ));

        return Inertia::render('Listings/Index', [
            'listings' => ListingResource::collection(
                $this->listings->liveListingsMatching($criteria, $request->integer('per_page', 15))
            ),
            'branches' => BranchResource::collection($this->listings->branches()),
            'propertyTypes' => PropertyType::options(),
            // Echoed back exactly as sent so the filter form re-seeds itself,
            // rather than the normalised values the query was built from.
            'filters' => $request->only('property_type', 'max_price', 'min_bedrooms', 'region'),
            'saveSearchUrl' => $this->listings->saveSearchUrl($criteria),
        ]);
    }

    /**
     * Only live listings are public. Drafts, under-offer and sold listings are
     * not exposed here, matching the index page.
     */
    public function show(Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Live, 404);

        return Inertia::render('Listings/Show', [
            'listing' => new ListingResource($listing),
        ]);
    }
}
