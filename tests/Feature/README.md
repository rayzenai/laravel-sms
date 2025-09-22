# Feature Tests for Laravel SMS Package

This directory contains comprehensive feature tests for the Laravel SMS package API endpoints.

## Test Coverage

### SendSmsTest.php
Tests for single SMS sending functionality:
- ✅ Sending single SMS and storing record in database
- ✅ Validation of required fields (recipient, message)
- ✅ Message length validation (max 1600 characters)
- ✅ Graceful handling of provider failures
- ✅ Storage of provider response data
- ✅ Correct JSON response structure

### SendBulkSmsTest.php
Tests for bulk SMS sending functionality:
- ✅ Sending bulk SMS and storing multiple records
- ✅ Validation of required fields for bulk operations
- ✅ Recipient format validation in bulk requests
- ✅ Handling partial failures (some succeed, some fail)
- ✅ Individual results for each recipient
- ✅ Complete failure handling
- ✅ Large bulk request processing (50+ recipients)

### SmsApiEndpointsTest.php
Tests for API endpoint behavior and response formats:
- ✅ Correct Content-Type headers (application/json)
- ✅ Consistent JSON response structure for single SMS
- ✅ Consistent JSON response structure for bulk SMS
- ✅ Error response structure consistency
- ✅ Laravel validation error format compliance
- ✅ API route prefix verification (/api/sms/*)
- ✅ Valid timestamp format in responses

## Running the Tests

To run all feature tests:
```bash
./vendor/bin/phpunit tests/Feature/
```

To run a specific test file:
```bash
./vendor/bin/phpunit tests/Feature/SendSmsTest.php
```

To run a specific test method:
```bash
./vendor/bin/phpunit tests/Feature/SendSmsTest.php --filter="it_can_send_single_sms_and_store_record"
```

## Test Architecture

The tests use:
- **Mockery** for mocking SMS providers
- **RefreshDatabase** trait for database isolation
- **Laravel's testing helpers** for API testing

Each test class extends the package's TestCase which sets up:
- In-memory SQLite database
- Automatic migration running
- Package service provider registration
