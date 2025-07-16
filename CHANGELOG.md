# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2025-01-16

### Added
- Nepali phone number validation with `NepaliPhoneNumber` rule
- Support for SwiftSMS provider for Nepal SMS gateway
- Bulk SMS sending capability with `sendBulk` method in providers
- Extended `SmsProviderInterface` with `sendBulk` method
- SwiftSmsProvider implementation with batch sending support
- Comprehensive unit tests for Nepali phone validation

### Changed
- Updated HttpProvider to implement sendBulk method
- Updated TwilioProvider to implement sendBulk method
- Enhanced SmsService to use provider's sendBulk method when available
- Improved validation in API controllers with Nepali phone format

### Fixed
- Fixed uninitialized property warnings in tests
- Corrected return type issues in provider mocks
- Fixed syntax errors in SmsProviderInterface

## [1.1.0] - Previous release

### Added
- Initial SMS service implementation
- Support for HTTP and Twilio providers
- Basic SMS sending functionality

## [1.0.0] - Initial release

### Added
- Package structure and foundation
- Configuration system
- Basic provider interface
