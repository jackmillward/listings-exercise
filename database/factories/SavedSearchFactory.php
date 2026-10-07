<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => null,
            'max_price' => fake()->numberBetween(2, 8) * 100_000,
            'min_bedrooms' => fake()->numberBetween(1, 4),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'region' => fake()->randomElement(['Manchester', 'Leeds', 'Bristol']),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['name' => $name]);
    }

    public function criteria(array $criteria): static
    {
        return $this->state(fn () => array_filter(
            $criteria,
            fn (mixed $value): bool => $value !== null,
        ));
    }
}
