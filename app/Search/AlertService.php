<?php

namespace App\Search;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingMatchedSavedSearch;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;

/**
 * Reads a user's saved-search alerts.
 *
 * Every read is scoped by notification type so that a notification added later
 * — a password reset, say — cannot leak onto the alerts page or into the nav
 * badge. Keeping that scope in one place is what stops the two drifting apart.
 */
class AlertService
{
    /**
     * @return DatabaseNotificationCollection<int, DatabaseNotification>
     */
    public function forUser(User $user): DatabaseNotificationCollection
    {
        $alerts = $user->notifications()
            ->where('type', ListingMatchedSavedSearch::class)
            ->latest()
            ->get();

        // One query for every listing the alerts refer to, set as a relation so
        // the resource can read whether a listing is still live without a query
        // per alert.
        $listings = Listing::query()
            ->findMany($alerts->pluck('data.listing_id')->filter()->all())
            ->keyBy('id');

        $alerts->each(function (DatabaseNotification $alert) use ($listings): void {
            $listingId = $alert->data['listing_id'] ?? null;

            $alert->setRelation('listing', is_int($listingId) ? $listings->get($listingId) : null);
        });

        return $alerts;
    }

    public function markAllReadForUser(User $user): void
    {
        $this->forUser($user)->markAsRead();
    }

    public function unreadCountFor(User $user): int
    {
        return $user->notifications()
            ->where('type', ListingMatchedSavedSearch::class)
            ->whereNull('read_at')
            ->count();
    }
}
