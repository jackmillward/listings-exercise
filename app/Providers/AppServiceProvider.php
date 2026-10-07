<?php

namespace App\Providers;

use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Search\AlertService;
use App\Search\ListingService;
use App\Search\SavedSearchService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SavedSearchService::class);
        $this->app->bind(AlertService::class);
        $this->app->bind(ListingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inertia props are consumed directly by Vue, so the extra "data"
        // envelope around every resource just gets in the way. Paginated
        // collections keep their data/links/meta structure regardless.
        JsonResource::withoutWrapping();

        Listing::observe(ListingObserver::class);
    }
}
