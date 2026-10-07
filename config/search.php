<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Saved Searches
    |--------------------------------------------------------------------------
    |
    | The cap is a plain literal rather than an env value: it is a product
    | decision about how much of the product a person should manage, and an env
    | value invites drift between what was intended and what is deployed.
    |
    | The criteria bounds are applied by both the saved-search form and the
    | listings index filter, so any saved search stays expressible as a filter.
    |
    */

    'saved_searches' => [

        'max_per_user' => 5,

        'criteria' => [
            'max_price' => [
                'min' => 0,
            ],
            'min_bedrooms' => [
                'min' => 0,
                'max' => 20,
            ],
            'region' => [
                'max_length' => 100,
            ],
        ],

    ],

];
