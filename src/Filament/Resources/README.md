# Laravel SMS Filament Resources

This directory contains Filament resources for managing SMS messages sent through the Laravel SMS package.

## Available Resources

### SentMessageResource

Provides a complete interface for viewing and managing sent SMS messages.

**Features:**
- List all sent messages with pagination
- View detailed message information
- Filter messages by status and date
- Search messages by recipient, message content
- View provider responses and metadata

**Table Columns:**
- ID
- Recipient
- Message (truncated with full tooltip)
- Status (with color badges)
- Created At
- Sender (hidden by default)
- Provider (hidden by default)  
- Sent At (hidden by default)

## Installation in Your Application

1. Ensure Filament is installed in your Laravel application:
```bash
composer require filament/filament:"^3.0"
```

2. Publish the Filament resources:
```bash
php artisan vendor:publish --tag=laravel-sms-filament
```

3. The resources will be published to `app/Filament/Resources/LaravelSms/`

4. If you want to customize the resources, you can modify the published files.

## Usage

After installation, the Sent Messages resource will appear in your Filament admin panel under the "SMS Management" navigation group.

### Permissions

To control access to the SMS resources, you can modify the `can*` methods in the published resource files:

```php
public static function canViewAny(): bool
{
    return auth()->user()->can('view-sent-messages');
}
```

### Customization

You can customize the resource by modifying the published files:
- Change table columns
- Add custom actions
- Modify form fields
- Add relationships
- Customize filters
