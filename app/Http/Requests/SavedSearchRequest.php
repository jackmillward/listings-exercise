<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Rules\NotDuplicateSavedSearch;
use App\Rules\SavedSearchLimit;
use App\Search\SearchCriteria;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class SavedSearchRequest extends FormRequest
{
    public const CRITERIA_KEYS = ['property_type', 'max_price', 'min_bedrooms', 'region'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => [
                'nullable',
                'integer',
                'min:'.config('search.saved_searches.criteria.max_price.min'),
            ],
            'min_bedrooms' => [
                'nullable',
                'integer',
                'min:'.config('search.saved_searches.criteria.min_bedrooms.min'),
                'max:'.config('search.saved_searches.criteria.min_bedrooms.max'),
            ],
            'region' => [
                'nullable',
                'string',
                'max:'.config('search.saved_searches.criteria.region.max_length'),
            ],
        ];
    }

    /**
     * The rules that need the database, plus the "at least one criterion" rule,
     * run only once the shape above has passed — Laravel skips after() callbacks
     * as soon as validation fails — so SearchCriteria never has to cope with an
     * unvalidated property type.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $userId = $this->user()?->getAuthIdentifier();

                // These judge saving, not rendering the form. On the create page
                // they would block a GET with a duplicate or cap error before the
                // user had done anything.
                if ($userId === null || ! $this->isMethod('POST')) {
                    return;
                }

                // Tested on the normalised criteria rather than on which keys the
                // request carried, so a criterion that normalises to unconstrained
                // cannot smuggle an unrestricted search past validation.
                if ($this->criteria()->isEmpty()) {
                    $validator->errors()->add('criteria', 'Set at least one filter before saving a search.');

                    return;
                }

                $rules = [
                    new NotDuplicateSavedSearch($userId, $this->criteria()),
                    new SavedSearchLimit($userId),
                ];

                foreach ($rules as $rule) {
                    $rule->validate('criteria', null, $this->failure($validator, 'criteria'));
                }
            },
        ];
    }

    /**
     * Bridges a rule's $fail callback onto the error bag, preserving the
     * signature Laravel's ValidationRule contract declares.
     *
     * @return Closure(string, string|null=): PotentiallyTranslatedString
     */
    private function failure(Validator $validator, string $key): Closure
    {
        return function (string $message) use ($validator, $key): PotentiallyTranslatedString {
            $validator->errors()->add($key, $message);

            return new PotentiallyTranslatedString($message, $validator->getTranslator());
        };
    }

    /**
     * The validated criteria, in the shape the model stores. Explicit keys
     * because a blank criterion is absent from the validated payload, while the
     * model expects the key to exist and hold null.
     *
     * @return array<string, mixed>
     */
    public function criteriaInput(): array
    {
        $validated = $this->validated();

        return [
            'property_type' => $validated['property_type'] ?? null,
            'max_price' => $validated['max_price'] ?? null,
            'min_bedrooms' => $validated['min_bedrooms'] ?? null,
            'region' => $validated['region'] ?? null,
        ];
    }

    public function criteria(): SearchCriteria
    {
        return SearchCriteria::fromArray($this->criteriaInput());
    }
}
