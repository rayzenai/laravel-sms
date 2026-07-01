<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | The name of the provider (a key in the `providers` array below) used to
    | send messages by default. Override per message with Sms::provider('name').
    |
    */
    'default' => env('SMS_PROVIDER', 'http'),

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
    | SMS Providers
    |--------------------------------------------------------------------------
    |
    | Every provider is registered here by name. Each entry names the `class`
    | that implements Rayzenai\LaravelSms\Providers\SmsProviderInterface plus
    | that provider's own credentials. To add a provider, write a class (extend
    | AbstractSmsProvider) and add an entry here — nothing else changes.
    |
    */
    'providers' => [
        'http' => [
            'class' => \Rayzenai\LaravelSms\Providers\HttpProvider::class,
            'api_base_url' => env('SMS_API_BASE_URL', 'https://api.example.com'),
            'api_key' => env('SMS_API_KEY', ''),
        ],
        'twilio' => [
            'class' => \Rayzenai\LaravelSms\Providers\TwilioProvider::class,
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM_NUMBER'),
        ],
        'swift' => [
            'class' => \Rayzenai\LaravelSms\Providers\SwiftSmsProvider::class,
            'organisation_code' => env('SWIFT_SMS_ORGANISATION_CODE'),
            'username' => env('SWIFT_SMS_USERNAME'),
            'password' => env('SWIFT_SMS_PASSWORD'),
        ],
        'aakash' => [
            'class' => \Rayzenai\LaravelSms\Providers\AakashSmsProvider::class,
            'auth_token' => env('AAKASH_SMS_AUTH_TOKEN'),
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

    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure user model integration for sending SMS to application users.
    | This allows you to select users from your database when sending SMS.
    |
    */
    'user_model' => [
        'enabled' => true,
        'class' => \App\Models\User::class,
        'phone_field' => 'phone', // The field that contains the phone number
        'name_field' => 'name', // The field to display as user name
        'searchable_fields' => ['name', 'email', 'phone'], // Fields to search when filtering users
    ],
];
