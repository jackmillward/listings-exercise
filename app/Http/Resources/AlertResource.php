<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->data['listing_id'] ?? null,
            'reference' => $this->data['reference'] ?? null,
            'address_line_1' => $this->data['address_line_1'] ?? null,
            'price' => $this->data['price'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            'is_live' => $this->listing() instanceof Listing
                && $this->listing()->status === ListingStatus::Live,
        ];
    }

    private function listing(): ?Model
    {
        return $this->relationLoaded('listing') ? $this->getRelation('listing') : null;
    }
}
