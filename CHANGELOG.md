# Changelog

All notable changes to this project will be documented in this file.

## [3.0.1] - 2024-09-22

### Fixed
- **Universal Phone Field Support**: Fixed SQL errors when phone fields are stored as bigint
  - Added intelligent field type detection that works with both string and bigint columns
  - Automatically adapts queries based on the actual database field type
  - No configuration needed - works out of the box with any phone field type
  - Prevents "invalid input syntax for type bigint" errors in PostgreSQL

## [3.0.0] - 2025-01-17

### Breaking Changes
- **MAJOR**: Upgraded to Filament v4 support - requires Filament 4.0 or higher
- **MAJOR**: Upgraded to Laravel 12 support - requires Laravel 12.0 or higher
- Changed from `Filament\Forms\Form` to `Filament\Schemas\Schema` for Filament v4 compatibility
- Updated all Filament components to use new v4 namespace structure

### Added
- **User Selection for Bulk SMS**: Send SMS to users from your database
  - Select individual users or all users with phone numbers
  - Automatic duplicate phone number detection and handling
  - Shows which users share the same phone number
  - Displays count of unique numbers vs total users
- **Improved UI Design**: Modern, clean interface with better visual hierarchy
  - Side-by-side toggle layout for better space utilization
  - Cleaner form sections without heavy card borders
  - Improved helper text and character counting
- **Configuration for User Model Integration**: Customizable user model settings
  - Configure which model to use for user data
  - Specify phone and name fields
  - Enable/disable user selection feature
- **Swift SMS Provider Enhancements**: Better logging and error handling for bulk SMS

### Changed
- Updated Filament resources to use `Filament\Schemas\Schema` instead of `Form`
- Improved bulk SMS handling with unique phone number filtering
- Enhanced form validation and user feedback
- Updated documentation with Filament v4 integration instructions
- Modernized UI components with better toggle designs

### Fixed
- Fixed Filament v4 compatibility issues with form schemas
- Fixed toggle components not responding correctly
- Fixed bulk SMS not working with Swift SMS provider
- Resolved namespace conflicts with Filament v4 components

## [1.2.3] - 2025-01-16

### Added
- Filament Send SMS page for sending single and bulk SMS from admin panel
- LaravelSmsPlugin for easy Filament integration
- Real-time character counter for SMS messages
- Toggle between single and bulk SMS modes in Filament
- Confirmation dialogs before sending SMS
- Success/error notifications in Filament
- SMS sending guidelines in the admin panel

### Changed
- Updated SentMessageResource navigation sort order
- Enhanced documentation with detailed Filament integration instructions

## [1.2.2] - 2025-01-16

### Added
- Fluent interface for SMS sending with `Sms::to()->message()->send()` syntax
- SmsMessageBuilder class for chainable method calls
- Support for both fluent and direct method calls

### Fixed
- Fixed "Call to undefined method to()" error when using fluent interface

## [1.2.1] - 2025-01-16

### Added
- SMS Facade for easier usage with `Sms::send()` and `Sms::sendBulk()` methods
- Updated documentation with Facade usage examples

### Fixed
- Fixed missing Facade class error

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
