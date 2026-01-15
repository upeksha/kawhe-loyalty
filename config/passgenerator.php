<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apple Wallet Pass Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for generating Apple Wallet passes (.pkpass files)
    |
    */

    'certificate_path' => env('CERTIFICATE_PATH', 'passgenerator/certs/certificate.p12'),
    'certificate_pass' => env('CERTIFICATE_PASS', ''),
    'wwdr_certificate' => env('WWDR_CERTIFICATE', 'passgenerator/certs/AppleWWDRCA.pem'),
    'pass_type_identifier' => env('APPLE_PASS_TYPE_IDENTIFIER', 'pass.com.kawhe.loyalty'),
    'team_identifier' => env('APPLE_TEAM_IDENTIFIER', ''),
    'organization_name' => env('APPLE_ORGANIZATION_NAME', 'Kawhe'),
];
