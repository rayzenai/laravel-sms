<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS API Base URL
    |--------------------------------------------------------------------------
    |
    | This value is the base URL for your SMS provider's API endpoint.
    | You should set this in your environment file as SMS_API_BASE_URL.
    |
    */
    'api_base_url' => env('SMS_API_BASE_URL', 'https://api.example.com'),

    /*
    |--------------------------------------------------------------------------
    | SMS API Key
    |--------------------------------------------------------------------------
    |
    | This is the API key used to authenticate with your SMS provider.
    | You should set this in your environment file as SMS_API_KEY.
    |
    */
    'api_key' => env('SMS_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Default SMS Sender
    |--------------------------------------------------------------------------
    |
    | This value is the default sender name or number that will be used
    | when sending SMS messages. You can override this on a per-message basis.
    |
    */
    'default_sender' => env('SMS_DEFAULT_SENDER', 'Laravel App'),

    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    |
    | Here you may specify which SMS provider you wish to use as your
    | default provider for sending messages. You may also configure
    | multiple providers and switch between them as needed.
    |
    */
    'default' => env('SMS_PROVIDER', 'default'),
    'default_provider' => env('SMS_PROVIDER_CLASS', \Rayzenai\LaravelSms\Providers\HttpProvider::class),

    /*
    |--------------------------------------------------------------------------
    | SMS Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the SMS providers used by your
    | application plus their respective settings. Several examples have
    | been configured for you and you are free to add your own.
    |
    */
    'providers' => [
        'http' => [
            'class' => \Rayzenai\LaravelSms\Providers\HttpProvider::class,
        ],
        'twilio' => [
            'class' => \Rayzenai\LaravelSms\Providers\TwilioProvider::class,
        ],
        'swift' => [
            'class' => \Rayzenai\LaravelSms\Providers\SwiftSmsProvider::class,
            'organisation_code' => env('SWIFT_SMS_ORGANISATION_CODE'),
            'username' => env('SWIFT_SMS_USERNAME'),
            'password' => env('SWIFT_SMS_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, all SMS messages will be logged for debugging purposes.
    | This is useful during development but should be disabled in production
    | unless you need to track SMS activity.
    |
    */
    'logging' => [
        'enabled' => env('SMS_LOGGING_ENABLED', false),
        'channel' => env('SMS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for SMS sending to prevent abuse and comply
    | with provider restrictions. Set to null to disable rate limiting.
    |
    */
    'rate_limit' => [
        'enabled' => env('SMS_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('SMS_MAX_PER_MINUTE', 60),
        'max_per_hour' => env('SMS_MAX_PER_HOUR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may specify the timeout in seconds for SMS API requests.
    | This prevents your application from hanging on slow API responses.
    |
    */
    'timeout' => env('SMS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SMS Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed SMS messages.
    | Set attempts to 1 to disable retries.
    |
    */
    'retry' => [
        'attempts' => env('SMS_RETRY_ATTEMPTS', 3),
        'delay' => env('SMS_RETRY_DELAY', 1000), // milliseconds
    ],
];
