<?php

namespace App\Search;

use App\Enums\PropertyType;
use App\Models\Listing;
use InvalidArgumentException;

/**
 * A set of listing criteria. A null criterion is unconstrained.
 *
 * Holds no matching logic: matching is SQL and lives in
 * {@see Listing::scopeMatching()}, so there is one definition of a match rather
 * than a PHP predicate here that could drift away from the query.
 */
final readonly class SearchCriteria
{
    public function __construct(
        public ?int $maxPrice,
        public ?int $minBedrooms,
        public ?PropertyType $propertyType,
        public ?string $region,
    ) {}

    /**
     * Build from the raw request shape, where a criterion may be absent, `null`
     * or a blank string.
     *
     * Callers validate first, so `from()` throwing on an unknown property type
     * is deliberate: dropping it would silently widen the search to everything.
     *
     * @param  array<string, mixed>  $criteria
     */
    public static function fromArray(array $criteria): self
    {
        $propertyType = $criteria['property_type'] ?? null;

        if (is_string($propertyType) && trim($propertyType) === '') {
            $propertyType = null;
        }

        $region = $criteria['region'] ?? null;
        $region = is_string($region) && trim($region) !== '' ? trim($region) : null;

        return new self(
            maxPrice: self::nonNegativeInt($criteria['max_price'] ?? null),
            minBedrooms: self::nonNegativeInt($criteria['min_bedrooms'] ?? null),
            propertyType: is_string($propertyType)
                ? PropertyType::from($propertyType)
                : $propertyType,
            region: $region,
        );
    }

    public function isEmpty(): bool
    {
        return $this->maxPrice === null
            && $this->minBedrooms === null
            && $this->propertyType === null
            && $this->region === null;
    }

    public function equals(self $other): bool
    {
        return $this->maxPrice === $other->maxPrice
            && $this->minBedrooms === $other->minBedrooms
            && $this->propertyType === $other->propertyType
            && $this->region === $other->region;
    }

    /**
     * The criteria as query-string parameters, omitting anything unconstrained.
     *
     * @return array<string, string>
     */
    public function toQueryArray(): array
    {
        // Null casts to '', so one filter drops every unset criterion.
        return array_filter([
            'property_type' => $this->propertyType->value ?? '',
            'min_bedrooms' => (string) $this->minBedrooms,
            'max_price' => (string) $this->maxPrice,
            'region' => $this->region ?? '',
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * Zero is a real constraint here — a max price of 0 matches nothing, and a
     * minimum of 0 bedrooms matches everything — so it is kept rather than
     * dropped. Only a blank value is unconstrained.
     *
     * A non-numeric string throws rather than coercing to 0: a silently
     * mis-parsed criterion is a wrong search, and a wrong search is the one
     * failure mode this feature cannot have.
     */
    private static function nonNegativeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (! ctype_digit(ltrim($trimmed, '+'))) {
            throw new InvalidArgumentException("Expected a non-negative integer, got '{$trimmed}'.");
        }

        return (int) $trimmed;
    }
}
