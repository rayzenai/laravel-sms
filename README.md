# Laravel SMS

A comprehensive Laravel package for sending SMS messages through various providers with Filament admin panel integration.

## Features

- 📱 Send single and bulk SMS messages
- 🔄 Multiple SMS provider support (HTTP, Twilio, SwiftSMS, etc.)
- 📊 Filament admin panel integration for SMS management
- 👥 User selection for bulk SMS - send to existing users in your database
- 🔍 Automatic duplicate phone number detection and handling
- 📝 SMS logs and tracking
- ⚡ Rate limiting and retry mechanisms
- 🛡️ Built-in error handling and logging
- 🇳🇵 Nepali phone number validation
- 📦 Bulk SMS optimization with provider-specific batch sending
- ✨ Modern UI with improved toggles and form layout

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12, or 13
- Filament 5.0 (for admin panel features)

## Installation

You can install the package via composer:

```bash
composer require rayzenai/laravel-sms
```

## Configuration

### Step 1: Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Rayzenai\LaravelSms\LaravelSmsServiceProvider" --tag="config"
```

This will publish a `laravel-sms.php` configuration file to your `config` directory.

### Step 2: Run Migrations

Run the migrations to create the necessary database tables:

```bash
php artisan migrate
```

### Step 3: Configure Environment Variables

Add the following variables to your `.env` file:

```env
# Pick the active provider by NAME — must match a key in the `providers`
# array of config/laravel-sms.php (http, twilio, swift, aakash, or your own).
SMS_PROVIDER=http
SMS_DEFAULT_SENDER="Your App Name"

# Generic HTTP provider
SMS_API_BASE_URL=https://api.your-sms-provider.com
SMS_API_KEY=your-api-key-here

# SwiftSMS provider (Nepal) — set SMS_PROVIDER=swift to use it
SWIFT_SMS_ORGANISATION_CODE=your-org-code
SWIFT_SMS_USERNAME=your-username
SWIFT_SMS_PASSWORD=your-password

# AakashSMS provider (Nepal) — set SMS_PROVIDER=aakash to use it
AAKASH_SMS_AUTH_TOKEN=your-aakash-token

# Twilio provider — set SMS_PROVIDER=twilio to use it
TWILIO_ACCOUNT_SID=your-account-sid
TWILIO_AUTH_TOKEN=your-auth-token
TWILIO_FROM_NUMBER=+1234567890

# Optional: Logging Configuration
SMS_LOGGING_ENABLED=true
SMS_LOG_CHANNEL=stack

# Optional: Rate Limiting
SMS_RATE_LIMIT_ENABLED=true
SMS_MAX_PER_MINUTE=60
SMS_MAX_PER_HOUR=1000

# Optional: Retry Configuration
SMS_RETRY_ATTEMPTS=3
SMS_RETRY_DELAY=1000
```

> **Selecting a provider is just `SMS_PROVIDER=<name>`.** The name maps to an entry
> in the `providers` array of `config/laravel-sms.php`. You can also switch per
> message at runtime with `Sms::provider('aakash')->send(...)`.

### Step 4: Configure User Model Integration (Optional)

If you want to enable sending SMS to users from your database, update the config file:

```php
// config/laravel-sms.php
'user_model' => [
    'enabled' => true,
    'class' => \App\Models\User::class,
    'phone_field' => 'phone', // The field that contains the phone number
    'name_field' => 'name',   // The field to display as user name
],
```

## Usage

### Phone Number Validation

The package now supports validation for Nepali phone numbers:
- Numbers must start with +977
- Must have 10 digits after the country code (e.g., +977 9801002468)

#### Example Validation Rule:

```php
'phone' => 'required|string|regex:/^\+977[9][0-9]{9}$/'
```

### Basic Usage

#### Sending a Single SMS

```php
use Rayzenai\LaravelSms\Services\SmsService;
use Rayzenai\LaravelSms\Facades\Sms;

// Method 1: Using the Facade with fluent interface (recommended)
$sentMessage = Sms::to('+9779801002468')
    ->message('Hello from Laravel SMS!')
    ->send();

// Method 2: Using the Facade with direct method call
$sentMessage = Sms::send('+9779801002468', 'Hello from Laravel SMS!');

// Method 3: Using dependency injection
public function sendSms(SmsService $smsService)
{
    try {
        $sentMessage = $smsService->send('+1234567890', 'Hello from Laravel SMS!');
        
        // Access sent message details
        echo "Message ID: " . $sentMessage->provider_message_id;
        echo "Status: " . $sentMessage->status;
    } catch (\Exception $e) {
        // Handle error
        Log::error('SMS sending failed: ' . $e->getMessage());
    }
}

// Method 3: Using service container
$smsService = app(SmsService::class);
$sentMessage = $smsService->send('+1234567890', 'Your message here');
```

#### Sending Bulk SMS

```php
use Rayzenai\LaravelSms\Facades\Sms;
use Rayzenai\LaravelSms\Services\SmsService;

// Method 1: Using the Facade with fluent interface (recommended)
$recipients = [
    '+9779801002468',
    '+9779812345678',
    '+9779898765432'
];

$sentMessages = Sms::to($recipients)
    ->message('Bulk message to all recipients!')
    ->sendBulk();

// Method 2: Using the service directly
$smsService = app(SmsService::class);

try {
    $sentMessages = $smsService->sendBulk($recipients, 'Bulk message to all recipients!');
    
    foreach ($sentMessages as $message) {
        echo "Recipient: {$message->recipient} - Status: {$message->status}\n";
    }
} catch (\Exception $e) {
    Log::error('Bulk SMS failed: ' . $e->getMessage());
```

#### Sending to a User (or any Model)

Implement the `HasSmsNumber` contract and add the `Smsable` trait. **The model
decides how its number is derived** — a column, an accessor, concatenating a country
code, or returning `null` when the record can't be reached. No config, no
assumptions about your schema.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Rayzenai\LaravelSms\Concerns\Smsable;
use Rayzenai\LaravelSms\Contracts\HasSmsNumber;

class User extends Authenticatable implements HasSmsNumber
{
    use Smsable;

    public function smsPhoneNumber(): ?string
    {
        // Return a sendable number (E.164 like +9779801002468 recommended),
        // or null if this user can't be reached by SMS.
        return $this->phone;
        // e.g. concat: '+' . ltrim($this->country_code, '+') . $this->phone
    }
}
```

Then send with the model itself:

```php
// Straight off the model — returns a SentMessage, or null if it has no number
$user->sendSMS('Your appointment is confirmed.');

// Send via a specific provider
$user->sendSMS('Sent via AakashSMS', 'aakash');

// Through the facade — models and plain strings can be mixed
Sms::to($user)->message('Hi')->send();
Sms::to($users)->message('Clinic closed tomorrow')->sendBulk();
```

- `smsPhoneNumber()` returning `null` means "unreachable": `$user->sendSMS()` is a
  safe no-op (returns `null`), and bulk sends **skip** that recipient automatically.
- A single `Sms::to($user)->send()` throws if the user resolves to no number; bulk
  filters them out.

#### Choosing a Provider at Runtime

The active provider comes from `SMS_PROVIDER`, but you can override it per message
without touching config:

```php
use Rayzenai\LaravelSms\Facades\Sms;

// Send this one message through AakashSMS regardless of the default provider
Sms::provider('aakash')->send('+9779801002468', 'Sent via AakashSMS');

Sms::provider('swift')
    ->to(['+9779801002468', '+9779812345678'])
    ->message('Sent via SwiftSMS')
    ->sendBulk();
```

#### Checking Provider Balance / Credit

Providers that support it (e.g. AakashSMS) implement `ReportsBalance`. Ask the active
provider — or a specific one — for its remaining credit:

```php
use Rayzenai\LaravelSms\Facades\Sms;

$balance = Sms::provider('aakash')->balance();
// ['credit' => 1234, 'response' => [...]]

echo "Remaining credit: {$balance['credit']}";
```

Calling `balance()` on a provider that doesn't support it throws an
`UnsupportedFeatureException`.

### Using in Controllers

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rayzenai\LaravelSms\Services\SmsService;

class NotificationController extends Controller
{
    private SmsService $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    public function sendWelcomeSms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^\+977[9][0-9]{9}$/',
            'name' => 'required|string'
        ]);
        
        try {
            $message = "Welcome {$request->name}! Thank you for joining us.";
            $sentMessage = $this->smsService->send($request->phone, $message);
            
            return response()->json([
                'success' => true,
                'message_id' => $sentMessage->provider_message_id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send SMS'
            ], 500);
        }
    }
}
```

## API Endpoints

The package provides the following API endpoints:

### Send Single SMS

**Endpoint:** `POST /api/sms/send`

**Request Body:**
```json
{
    "recipient": "+1234567890",
    "message": "Your SMS message here"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "recipient": "+1234567890",
        "message": "Your SMS message here",
        "status": "sent",
        "provider_message_id": "SMS123456",
        "sent_at": "2024-01-01T12:00:00Z"
    }
}
```

### Send Bulk SMS

**Endpoint:** `POST /api/sms/send-bulk`

**Request Body:**
```json
{
    "recipients": ["+1234567890", "+0987654321"],
    "message": "Bulk SMS message"
}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "recipient": "+1234567890",
            "status": "sent",
            "provider_message_id": "SMS123456"
        },
        {
            "recipient": "+0987654321",
            "status": "sent",
            "provider_message_id": "SMS123457"
        }
    ]
}
```

## Filament Integration

### Step-by-Step Filament Setup

#### 1. Install Filament (if not already installed)

```bash
composer require filament/filament:"^5.0"
php artisan filament:install --panels
```

#### 2. Register the SMS Plugin

In Filament v5, you'll need to register the `LaravelSmsPlugin` in your `Panel` service provider:

```php
use Rayzenai\LaravelSms\LaravelSmsPlugin;

// In app/Providers/Filament/AdminPanelProvider.php or your panel provider:
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->plugins([
            LaravelSmsPlugin::make(),
        ]);
}
```

#### 3. Access the SMS Management

Once you have registered the plugin (or the `SentMessageResource` directly), you
will see **SMS Management → Sent Messages** in the admin panel navigation.

1. Navigate to your Filament admin panel (typically `/admin`)
2. Open **Sent Messages** under **SMS Management** to browse/filter the log.
3. Click **Send SMS** (the resource's create button) to compose and send.

> Sending lives on the resource's **create** screen — creating a "sent message" *is*
> sending one. There is no separate page to register. With Filament Shield, access is
> gated by the resource's `Create` permission (composing/sending) and `ViewAny`/`View`
> (reading the log) — no bespoke page permission needed.

#### Filament Features:

**Send SMS (the "create" screen):**
- Single SMS sending with phone number validation
- Bulk SMS sending to multiple recipients
- **User selection mode for bulk SMS**
  - Select users from your database
  - Automatic duplicate phone number detection
  - Shows which users share the same phone number
  - "Select All" option for all unique phone numbers
  - Displays count of unique numbers vs total users
- Real-time character count for messages (160 character limit)
- Toggle between single and bulk SMS modes
- Toggle between manual entry and user selection (for bulk mode)
- Nepali phone number validation (+977 format)
- Success/error notifications

**Sent Messages list/view:**
- View all sent SMS messages in a table
- Filter by status (pending, sent, failed, delivered)
- Filter by date range
- Search by recipient or message content
- View detailed SMS information
- Bulk delete functionality
- Export SMS logs

### Customizing Filament Resources

If you need to customize the Filament resources, you can publish them:

```bash
php artisan vendor:publish --provider="Rayzenai\LaravelSms\LaravelSmsServiceProvider" --tag="filament-resources"
```

Then modify the published resources in `app/Filament/Resources/SentMessageResource.php`.

## Advanced Configuration

### Providers Registry

Every provider is registered by **name** in `config/laravel-sms.php`. Each entry
names the `class` and carries that provider's own credentials. `SMS_PROVIDER`
selects which one is active by default.

```php
'default' => env('SMS_PROVIDER', 'http'),

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
```

The manager instantiates the active provider and injects its config array (plus the
shared `timeout` and `default_sender`). Adding a provider is just a class plus an
entry here — see [Creating Custom SMS Providers](#creating-custom-sms-providers).

### Rate Limiting

Rate limiting is enabled by default. Configure it in your `.env`:

```env
SMS_RATE_LIMIT_ENABLED=true
SMS_MAX_PER_MINUTE=60
SMS_MAX_PER_HOUR=1000
```

### Logging

All SMS activities are logged when enabled:

```env
SMS_LOGGING_ENABLED=true
SMS_LOG_CHANNEL=sms
```

You can create a custom log channel in `config/logging.php`:

```php
'channels' => [
    // ...
    'sms' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sms.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

## Creating Custom SMS Providers

Adding a provider takes two steps: **write a class** and **register it**. That's the
whole extension surface.

### 1. Write the provider

Extend `AbstractSmsProvider` and implement `send()`. You get the shared `$config`,
`$timeout`, and `$sender`, plus a default `sendBulk()` that loops `send()` — so a
simple provider only implements one method.

```php
namespace App\Sms\Providers;

use Illuminate\Support\Facades\Http;
use Rayzenai\LaravelSms\Providers\AbstractSmsProvider;

class CustomProvider extends AbstractSmsProvider
{
    public function send(string $recipient, string $message): array
    {
        $response = Http::timeout($this->timeout)
            ->withToken($this->config('api_key'))
            ->post($this->config('api_url'), [
                'to' => $recipient,
                'body' => $message,
                'from' => $this->sender,
            ]);

        return [
            'sid' => $response->json('id'),
            'status' => $response->successful() ? 'sent' : 'failed',
            'response' => $response->json(),
        ];
    }
}
```

- **Native bulk?** Override `sendBulk()` and return
  `['status' => ..., 'batch_id' => ..., 'recipients_count' => ..., 'response' => ...]`.
- **Report credit/balance?** Also `implements ReportsBalance` and add a `balance()`
  method returning `['credit' => ..., 'response' => ...]` — then `Sms::balance()`
  works for your provider.

### 2. Register it

```php
'providers' => [
    'custom' => [
        'class' => \App\Sms\Providers\CustomProvider::class,
        'api_url' => env('CUSTOM_SMS_URL'),
        'api_key' => env('CUSTOM_SMS_KEY'),
    ],
],
```

Then set `SMS_PROVIDER=custom` (or use `Sms::provider('custom')` per message).

## Testing

```bash
composer test
```

## Credits

- [Rayzen AI](https://github.com/rayzenai)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
