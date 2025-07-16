# Laravel SMS

A comprehensive Laravel package for sending SMS messages through various providers with Filament admin panel integration.

## Features

- 📱 Send single and bulk SMS messages
- 🔄 Multiple SMS provider support (HTTP, Twilio, etc.)
- 📊 Filament admin panel integration for SMS management
- 📝 SMS logs and tracking
- ⚡ Rate limiting and retry mechanisms
- 🛡️ Built-in error handling and logging

## Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher
- Filament 3.0 (for admin panel features)

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
# SMS Provider Configuration
SMS_PROVIDER=http
SMS_API_BASE_URL=https://api.your-sms-provider.com
SMS_API_KEY=your-api-key-here
SMS_DEFAULT_SENDER="Your App Name"

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

// Using dependency injection
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

// Using service container
$smsService = app(SmsService::class);
$sentMessage = $smsService->send('+1234567890', 'Your message here');
```

#### Sending Bulk SMS

```php
use Rayzenai\LaravelSms\Services\SmsService;

$smsService = app(SmsService::class);

$recipients = [
    '+1234567890',
    '+0987654321',
    '+1111111111'
];

try {
    $sentMessages = $smsService->sendBulk($recipients, 'Bulk message to all recipients!');
    
    foreach ($sentMessages as $message) {
        echo "Recipient: {$message->recipient} - Status: {$message->status}\n";
    }
} catch (\Exception $e) {
    Log::error('Bulk SMS failed: ' . $e->getMessage());
}
```

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
composer require filament/filament:"^3.0"
php artisan filament:install --panels
```

#### 2. Register the SMS Resource

The package automatically registers the `SentMessageResource` with your Filament panel. After installation, you'll see a new "Sent Messages" section in your Filament admin panel.

#### 3. Access SMS Management

1. Navigate to your Filament admin panel (typically `/admin`)
2. Look for "Sent Messages" in the navigation
3. From here you can:
   - View all sent SMS messages
   - Filter messages by status, recipient, or date
   - View detailed information about each message
   - Export SMS logs

### Customizing Filament Resources

If you need to customize the Filament resources, you can publish them:

```bash
php artisan vendor:publish --provider="Rayzenai\LaravelSms\LaravelSmsServiceProvider" --tag="filament-resources"
```

Then modify the published resources in `app/Filament/Resources/SentMessageResource.php`.

## Advanced Configuration

### Using Multiple SMS Providers

You can configure multiple SMS providers in `config/laravel-sms.php`:

```php
'providers' => [
    'twilio' => [
        'class' => \Rayzenai\LaravelSms\Providers\TwilioProvider::class,
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
    ],
    'http' => [
        'class' => \Rayzenai\LaravelSms\Providers\HttpProvider::class,
        'api_key' => env('SMS_API_KEY'),
        'api_url' => env('SMS_API_BASE_URL'),
    ],
],
```

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

To create a custom SMS provider, implement the `SmsProviderInterface`:

```php
namespace App\Sms\Providers;

use Rayzenai\LaravelSms\Providers\SmsProviderInterface;

class CustomProvider implements SmsProviderInterface
{
    public function send(string $to, string $message, ?string $from = null): array
    {
        // Your implementation here
        return [
            'status' => 'sent',
            'sid' => 'unique-message-id',
            'response' => []
        ];
    }
}
```

Then register it in your configuration:

```php
'providers' => [
    'custom' => [
        'class' => \App\Sms\Providers\CustomProvider::class,
    ],
],
```

## Testing

```bash
composer test
```

## Credits

- [Rayzen AI](https://github.com/rayzenai)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
