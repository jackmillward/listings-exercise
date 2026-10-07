<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListingIndexRequest extends FormRequest
{
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
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:'.config('search.saved_searches.criteria.max_price.min')],
            'min_bedrooms' => [
                'nullable',
                'integer',
                'min:'.config('search.saved_searches.criteria.min_bedrooms.min'),
                'max:'.config('search.saved_searches.criteria.min_bedrooms.max'),
            ],
            'region' => ['nullable', 'string', 'max:'.config('search.saved_searches.criteria.region.max_length')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
