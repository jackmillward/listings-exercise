<?php

namespace App\Rules;

use App\Models\SavedSearch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a saved search once the user is at the configured cap. Without it a
 * user can save every combination of criteria they can think of, and each new
 * listing could then match several of them.
 */
class SavedSearchLimit implements ValidationRule
{
    public function __construct(private readonly int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $limit = (int) config('search.saved_searches.max_per_user');

        if (SavedSearch::query()->where('user_id', $this->userId)->count() >= $limit) {
            $fail("You can save up to {$limit} searches. Delete one to save another.");
        }
    }
}
