<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A new listing matched one of a user's saved searches.
 *
 * The payload snapshots the listing rather than holding only its id, so a later
 * channel needs no schema change and the alerts page still reads correctly
 * after the listing row is gone.
 *
 * Not ShouldQueue: no worker runs here, so a queued notification would never
 * be delivered.
 */
class ListingMatchedSavedSearch extends Notification
{
    use Queueable;

    public function __construct(public readonly Listing $listing) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'listing_id' => $this->listing->id,
            'reference' => $this->listing->reference,
            'address_line_1' => $this->listing->address_line_1,
            'price' => $this->listing->price,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A new listing matches your saved search')
            ->line($this->listing->address_line_1)
            ->action('View the listing', route('listings.show', $this->listing));
    }
}
