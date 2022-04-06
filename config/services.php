<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'simpro' => [
        'api_url' => env('SIMPRO_API_URL', 'https://gassure.simprocloud.com'),
        'token' => env('SIMPRO_TOKEN', 'fake_simpro_token'),
        'company_id' => (int) env('SIMPRO_COMPANY_ID', 0),
        'customer_id' => (int) env('SIMPRO_CUSTOMER_ID', 83),
        'uprn_field_id' => (int) env('SIMPRO_UPRN_FIELD_ID', 2),
        'job_initial_status_id' => (int) env('SIMPRO_JOB_INITIAL_STATUS_ID', 7),
        'job_type_codes_map' => json_decode(env('SIMPRO_JOB_TYPE_CODES_MAP', '{"SORJ": 4, "GASE": 4, "GSERV": 2}'), true),
        'default_job_type_code_id' => (int) env('SIMPRO_DEFAULT_JOB_TYPE_CODE', 2)
    ],

    'riverside' => [
        'api_url' => env('RIVERSIDE_API_URL', 'https://oh.services.riverside.org.uk/test.openaccess/webservice.p'),
        'user_name' => env('RIVERSIDE_USER_NAME', 'fake_riverside_user_name'),
        'password' => env('RIVERSIDE_PASSWORD', 'fake_riverside_password'),
        'secret' => env('RIVERSIDE_SECRET', 'fake_riverside_secret'),
    ]
];
