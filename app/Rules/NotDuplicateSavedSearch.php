<?php

namespace App\Rules;

use App\Models\SavedSearch;
use App\Search\SearchCriteria;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDuplicateSavedSearch implements ValidationRule
{
    public function __construct(
        private readonly int $userId,
        private readonly SearchCriteria $criteria,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isDuplicate = SavedSearch::query()
            ->where('user_id', $this->userId)
            ->get()
            ->contains(fn (SavedSearch $savedSearch): bool => $savedSearch->criteria()->equals($this->criteria));

        if ($isDuplicate) {
            $fail('You have already saved a search with these criteria.');
        }
    }
}
