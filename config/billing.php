<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan entitlements
    |--------------------------------------------------------------------------
    |
    | null = unlimited for that dimension.
    | business is reserved for a future Stripe price — not sold yet.
    |
    */

    'plans' => [
        'free' => [
            'label' => 'Free',
            'stores' => 1,
            'programs_per_store' => 1,
            'customers_per_program' => 100,
        ],
        'pro' => [
            'label' => 'Pro',
            'stores' => 3,
            'programs_per_store' => 5,
            'customers_per_program' => null,
        ],
        'business' => [
            'label' => 'Business',
            'stores' => null,
            'programs_per_store' => null,
            'customers_per_program' => null,
            'coming_soon' => true,
        ],
    ],

];
