<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use App\Search\SearchCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearchCriteriaTest extends TestCase
{
    public function test_blank_values_are_treated_as_unconstrained(): void
    {
        $criteria = SearchCriteria::fromArray([
            'max_price' => '',
            'min_bedrooms' => '',
            'property_type' => '',
            'region' => '',
        ]);

        $this->assertTrue($criteria->isEmpty());
    }

    public function test_absent_keys_are_treated_as_unconstrained(): void
    {
        $this->assertTrue(SearchCriteria::fromArray([])->isEmpty());
    }

    /**
     * Zero is a constraint, not an absence of one: a maximum of £0 matches no
     * listing and a minimum of 0 bedrooms matches all of them. Reading it as
     * unconstrained would silently widen the search to everything.
     */
    #[DataProvider('zeroValuesProvider')]
    public function test_zero_is_kept_as_a_constraint(mixed $value): void
    {
        $criteria = SearchCriteria::fromArray([
            'max_price' => $value,
            'min_bedrooms' => $value,
        ]);

        $this->assertSame(0, $criteria->maxPrice);
        $this->assertSame(0, $criteria->minBedrooms);
        $this->assertFalse($criteria->isEmpty());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function zeroValuesProvider(): array
    {
        return [
            'integer zero' => [0],
            'string zero' => ['0'],
            'padded string zero' => [' 0 '],
        ];
    }

    public function test_a_non_numeric_criterion_is_rejected_rather_than_coerced(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SearchCriteria::fromArray(['max_price' => '300,000']);
    }

    public function test_criteria_are_normalised_to_typed_values(): void
    {
        $criteria = SearchCriteria::fromArray([
            'max_price' => '300000',
            'min_bedrooms' => '2',
            'property_type' => 'flat',
            'region' => '  Manchester  ',
        ]);

        $this->assertSame(300000, $criteria->maxPrice);
        $this->assertSame(2, $criteria->minBedrooms);
        $this->assertSame(PropertyType::Flat, $criteria->propertyType);
        $this->assertSame('Manchester', $criteria->region);
        $this->assertFalse($criteria->isEmpty());
    }

    public function test_equals_compares_criteria_not_string_spelling(): void
    {
        $this->assertTrue(
            SearchCriteria::fromArray(['max_price' => '300000', 'region' => 'Leeds'])
                ->equals(SearchCriteria::fromArray(['max_price' => 300000, 'region' => 'Leeds']))
        );
    }

    public function test_equals_is_false_when_a_single_criterion_differs(): void
    {
        $this->assertFalse(
            SearchCriteria::fromArray(['max_price' => '300000'])
                ->equals(SearchCriteria::fromArray(['max_price' => '400000']))
        );
    }

    /**
     * A stricter search is a different search: saved "Leeds" must not block
     * saving "Leeds, max 300k", or the greyed-out button would wrongly appear.
     */
    public function test_a_stricter_search_is_not_equal(): void
    {
        $this->assertFalse(
            SearchCriteria::fromArray(['region' => 'Leeds'])
                ->equals(SearchCriteria::fromArray(['region' => 'Leeds', 'max_price' => '300000']))
        );
    }

    public function test_to_query_array_omits_unconstrained_criteria(): void
    {
        $this->assertSame(
            ['max_price' => '300000', 'region' => 'Leeds'],
            SearchCriteria::fromArray(['max_price' => '300000', 'region' => 'Leeds'])->toQueryArray()
        );
    }

    public function test_to_query_array_is_empty_for_an_empty_criteria_set(): void
    {
        $this->assertSame([], SearchCriteria::fromArray([])->toQueryArray());
    }

    public function test_to_query_array_round_trips_through_from_array(): void
    {
        $original = SearchCriteria::fromArray([
            'max_price' => '300000',
            'min_bedrooms' => '2',
            'property_type' => 'flat',
            'region' => 'Leeds',
        ]);

        $this->assertTrue($original->equals(SearchCriteria::fromArray($original->toQueryArray())));
    }
}
