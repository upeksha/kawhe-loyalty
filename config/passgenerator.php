<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apple Wallet Pass Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for generating Apple Wallet passes (.pkpass files)
    | This config matches the byte5/laravel-passgenerator package expectations
    |
    */

    'config_disk' => env('PASSGENERATOR_CONFIG_DISK'), // The disk to use for storing the pass configuration files and certificates

    'certificate_store_path' => env('CERTIFICATE_PATH', 'passgenerator/certs/certificate.p12'), // The path to the certificate store (a valid PKCS#12 file)
    'certificate_store_password' => env('CERTIFICATE_PASS', ''), // The password to unlock the certificate store
    'wwdr_certificate_path' => env('WWDR_CERTIFICATE', 'passgenerator/certs/AppleWWDRCA.pem'), // Get from here https://www.apple.com/certificateauthority/ and export to PEM

    'storage_disk' => env('PASSGENERATOR_STORAGE_DISK', 'local'), // The disk to use for storing the pass files
    'storage_path' => env('PASSGENERATOR_STORAGE_PATH', 'passgenerator/passes'), // The path to store the pass files on the disk (NOT certs!)

    'pass_type_identifier' => env('APPLE_PASS_TYPE_IDENTIFIER', env('PASS_TYPE_IDENTIFIER', 'pass.com.kawhe.loyalty')),
    'organization_name' => env('APPLE_ORGANIZATION_NAME', env('ORGANIZATION_NAME', 'Kawhe')),
    'team_identifier' => env('APPLE_TEAM_IDENTIFIER', env('TEAM_IDENTIFIER', '')),
];
