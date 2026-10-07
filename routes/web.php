<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

Route::prefix('saved-searches')->name('saved-searches.')->group(function (): void {
    Route::get('/', [SavedSearchController::class, 'index'])->name('index');
    Route::get('/create', [SavedSearchController::class, 'create'])->name('create');
    Route::post('/', [SavedSearchController::class, 'store'])->name('store');
    Route::delete('/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('destroy');

    // Alerts hang off saved searches rather than sitting at the root: an alert
    // is the result of a saved search matching a new listing, so they are read
    // and managed in the same place.
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/read', [AlertController::class, 'markAllRead'])->name('alerts.read');
});
