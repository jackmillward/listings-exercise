<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Search\SearchCriteria;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'max_price',
        'min_bedrooms',
        'property_type',
        'region',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'property_type' => PropertyType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The criteria this saved search represents.
     */
    public function criteria(): SearchCriteria
    {
        return SearchCriteria::fromArray([
            'max_price' => $this->max_price,
            'min_bedrooms' => $this->min_bedrooms,
            'property_type' => $this->property_type,
            'region' => $this->region,
        ]);
    }
}
