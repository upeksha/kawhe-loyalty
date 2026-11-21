<?php

return [
    'stripe_reporting' => env('ADMIN_STRIPE_REPORTING', false),
    // Optional reporting-only key; never replaces the checkout/webhook credentials.
    'stripe_reporting_key' => env('ADMIN_STRIPE_REPORTING_KEY'),
    // Explicit classification only; never guess test data from a café's name.
    'test_store_ids' => array_values(array_filter(array_map('intval', explode(',', env('ADMIN_TEST_STORE_IDS', ''))))),
];
