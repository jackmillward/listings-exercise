<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Http\Requests\SavedSearchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use App\Search\ListingService;
use App\Search\SavedSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function __construct(
        private readonly SavedSearchService $savedSearches,
        private readonly ListingService $listings,
    ) {}

    /**
     * The current user's saved searches.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection(
                $this->savedSearches->forUser($this->user($request))
            ),
        ]);
    }

    public function create(SavedSearchRequest $request): Response
    {
        return Inertia::render('SavedSearches/Create', [
            // The same branches as the listings page, so both forms offer the
            // same areas.
            'branches' => BranchResource::collection($this->listings->branches()),
            'propertyTypes' => PropertyType::options(),
            // Echoed back as sent, so the form shows the search the user was
            // looking at rather than the normalised values the query used.
            'criteria' => $request->only(SavedSearchRequest::CRITERIA_KEYS),
        ]);
    }

    public function store(SavedSearchRequest $request): RedirectResponse
    {
        $this->savedSearches->saveFor(
            $this->user($request),
            $request->validated('name'),
            $request->criteriaInput(),
        );

        return redirect()
            ->route('saved-searches.index')
            ->with('status', 'Search saved');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($this->savedSearches->deleteFor($request->user(), $savedSearch), 404);

        return back()->with('status', 'Search deleted');
    }
}
