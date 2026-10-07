<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The bounds are interpolated into rules as 'min:'.config(...). A renamed key
 * yields 'min:', which is not an error Laravel reports — it just stops enforcing
 * anything. Extends Tests\TestCase because reading config needs the container.
 */
class SearchConfigTest extends TestCase
{
    #[DataProvider('configKeyProvider')]
    public function test_the_config_key_resolves_to_a_non_negative_number(string $key): void
    {
        $value = config($key);

        $this->assertIsNumeric($value, "config({$key}) is missing or not numeric.");
        $this->assertGreaterThanOrEqual(0, $value, "config({$key}) must not be negative.");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function configKeyProvider(): array
    {
        return [
            'max price minimum' => ['search.saved_searches.criteria.max_price.min'],
            'bedrooms minimum' => ['search.saved_searches.criteria.min_bedrooms.min'],
            'bedrooms maximum' => ['search.saved_searches.criteria.min_bedrooms.max'],
            'region length' => ['search.saved_searches.criteria.region.max_length'],
            'saved searches per user' => ['search.saved_searches.max_per_user'],
        ];
    }

    public function test_a_saved_search_may_not_demand_more_bedrooms_than_are_allowed(): void
    {
        $this->assertLessThan(
            config('search.saved_searches.criteria.min_bedrooms.max'),
            config('search.saved_searches.criteria.min_bedrooms.min'),
        );
    }
}
