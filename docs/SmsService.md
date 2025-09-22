# SmsService Documentation

## Overview

The `SmsService` class provides methods to send SMS messages through a mock API integration. It supports both single and bulk SMS sending with comprehensive error handling and response logging.

## Features

- Single SMS sending
- Bulk SMS sending
- Automatic message logging to database
- Configurable API endpoints
- Error handling and retry logic
- HTTP timeout configuration
- Optional request/response logging

## Configuration

The service uses the following configuration values from `config/laravel-sms.php`:

- `api_base_url`: Base URL for the SMS API
- `api_key`: API authentication key
- `default_sender`: Default sender name/number
- `timeout`: HTTP request timeout in seconds
- `logging.enabled`: Enable/disable logging
- `logging.channel`: Log channel to use

## Methods

### send(string $recipient, string $message): SentMessage

Sends an SMS to a single recipient.

**Parameters:**
- `$recipient`: The recipient's phone number
- `$message`: The message content

**Returns:**
- `SentMessage`: Model instance with send details

**Example:**
```php
$smsService = app(SmsService::class);
$sentMessage = $smsService->send('+1234567890', 'Hello World!');
```

### sendBulk(array $recipients, string $message): Collection

Sends the same SMS to multiple recipients.

**Parameters:**
- `$recipients`: Array of recipient phone numbers
- `$message`: The message content

**Returns:**
- `Collection`: Collection of SentMessage models

**Example:**
```php
$smsService = app(SmsService::class);
$sentMessages = $smsService->sendBulk(['+1234567890', '+0987654321'], 'Bulk message!');
```

## API Endpoints

The service expects the following API endpoints:

### Single SMS: POST /send
**Request:**
```json
{
    "recipient": "+1234567890",
    "message": "Your message here",
    "sender": "YourApp"
}
```

**Response:**
```json
{
    "success": true,
    "message_id": "msg_123456",
    "status": "sent"
}
```

### Bulk SMS: POST /send-bulk
**Request:**
```json
{
    "recipients": ["+1234567890", "+0987654321"],
    "message": "Your message here",
    "sender": "YourApp"
}
```

**Response (with individual results):**
```json
{
    "success": true,
    "results": [
        {"message_id": "msg_001", "status": "sent"},
        {"message_id": "msg_002", "status": "failed", "error": "Invalid number"}
    ]
}
```

**Response (without individual results):**
```json
{
    "success": true,
    "batch_id": "batch_789"
}
```

## Database Storage

All sent messages are stored in the `sent_messages` table with the following fields:
- `recipient`: Phone number
- `message`: Message content
- `sender`: Sender name/number
- `status`: 'sent' or 'failed'
- `provider`: Provider name from config
- `provider_message_id`: Message ID from API
- `provider_response`: Full API response (JSON)
- `sent_at`: Timestamp

## Error Handling

- Failed API requests are caught and logged
- Failed messages are still saved to the database with 'failed' status
- In bulk sends, individual failures don't stop the entire batch
- HTTP exceptions are properly handled and logged

## Testing

The service can be easily tested using Laravel's HTTP fake:

```php
Http::fake([
    'https://api.example.com/send' => Http::response([
        'success' => true,
        'message_id' => 'test_123'
    ])
]);

$sentMessage = $smsService->send('+1234567890', 'Test message');
```
