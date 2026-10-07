<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The claim that a listing has been announced to a user.
 *
 * Exists so "one alert per user per listing" is a database invariant rather
 * than something the sending path is trusted to remember: the unique index
 * rejects the second claim, and a listing returning to the market therefore
 * cannot re-alert someone who already knows about it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $listing_id
 */
class ListingAlert extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
