# Changelog

All notable changes to this project will be documented in this file.

## [6.1.0] - 2026-07-01

### Added
- **User Segments.** A saved, named query over your user table that you can send
  bulk SMS to. The query definition is stored (never the results), so a segment's
  audience is recomputed live every time.
  - **New `sms_segments` table** — `name`, `conditions` (the query tree),
    `previous_count`, and `last_used_at`.
  - **Compact condition builder** — a custom, Alpine-powered field (not Filament's
    Builder) so it stays dense: type a field name, pick an operator, enter a value,
    choose AND/OR, and nest conditions in **Groups** for `( )`. It shows a **live
    match count** as you edit. Used both in the `SmsSegmentResource` (create/edit)
    and inline on the Send SMS screen.
  - **Send to a segment** — the Send SMS screen gains two recipient sources:
    **"Saved segment"** (pick an existing one) and **"Build a segment"** (compose
    conditions inline and send in one go). Picking/among either shows the live
    match count; a saved-segment send stamps `last_used_at` / `previous_count`.
    Segment rows also have a **"Send SMS"** action that deep-links into the send
    form pre-selected.
  - **`SegmentQuery`** evaluator — the single, safe place a segment becomes SQL:
    field names must be real columns on the user table (checked against the live
    schema), operators come from a fixed allowlist, and values always travel as
    bindings. Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `in`
    (comma list), `is_set`, `is_empty`.
  - `SmsSegment` model exposes `matchCount()` and `recipients()`; recipients are
    resolved via `smsPhoneNumber()` when the user model implements `HasSmsNumber`,
    otherwise the configured phone field.

## [6.0.2] - 2026-07-01

### Fixed
- **View page showed `[object Object]` for `provider_response`.** The `KeyValue`
  component can't render nested payloads (e.g. Aakash's `data.valid[]`). It now
  pretty-prints the full response as read-only JSON.

## [6.0.1] - 2026-07-01

### Fixed
- **Sent Messages table: the row "View" action did nothing.** It was a bare
  `Action::make('view')` with no behavior. Replaced with `ViewAction::make()`, which
  links to the record's view page (clicking the row already worked).

## [6.0.0] - 2026-07-01

### Breaking Changes
- **Provider selection is now name-based via a driver manager.** `SMS_PROVIDER`
  (e.g. `aakash`, `swift`, `http`) now actually selects the provider by resolving
  the matching entry in the `providers` registry. Previously `SMS_PROVIDER` was
  ignored and selection secretly used `SMS_PROVIDER_CLASS` / `default_provider`.
- **Removed** the `default_provider` config key and the `SMS_PROVIDER_CLASS` env
  var. Set `SMS_PROVIDER=<name>` instead.
- **Provider constructors now receive their config array** — `__construct(array $config)`
  (provided by the new `AbstractSmsProvider` base). Custom providers must extend
  `AbstractSmsProvider` (or accept `array $config`) instead of reading global
  `config()` themselves.
- **Top-level `api_base_url` / `api_key` config keys moved** into the `http`
  provider entry (`providers.http.api_base_url` / `.api_key`). The `SMS_API_BASE_URL`
  and `SMS_API_KEY` env var names are unchanged.
- The `provider` column on `sent_messages` now stores the provider **name**
  (e.g. `aakash`) instead of the provider class name.
- **Filament: the standalone `SendSms` page is removed.** Sending is now the
  **create** screen of `SentMessageResource` ("Send SMS"), so the plugin registers
  a single resource. With Filament Shield this means access is gated by the
  resource's `Create` permission instead of a page permission — grant `Create` on
  Sent Messages to roles that should send. The read-only `edit` page was also
  dropped (sent messages are immutable logs; delete is available from the view).

### Added
- **AakashSMS provider** (`AakashSmsProvider`) for the AakashSMS Nepal gateway —
  single (v3), bulk (v4), and balance/credit lookup. Strips `+977` to the local
  10-digit format the gateway expects.
- **`SmsManager`** driver registry that resolves providers by name and injects
  each provider's config.
- **`AbstractSmsProvider`** base class: shared config/timeout/sender handling and a
  default `sendBulk()` that loops `send()`, so a new provider only implements
  `send()`.
- **`ReportsBalance`** capability interface + `Sms::balance()` for providers that
  can report remaining credit (AakashSMS). Unsupported providers throw
  `UnsupportedFeatureException`.
- **Runtime provider override**: `Sms::provider('aakash')->send(...)` sends through a
  specific provider regardless of the configured default.
- **Model recipients**: a `HasSmsNumber` contract + `Smsable` trait let a model own
  how its phone number is derived. `Sms::to($user)` (and mixed model/string arrays)
  and `$user->sendSMS($message)` now work; `smsPhoneNumber()` returning `null` skips
  the recipient (bulk) or makes `sendSMS()` a no-op.

### Changed
- `Sms` facade forwards all calls (`to`, `send`, `sendBulk`, `provider`, `balance`)
  to `SmsService` via the base Laravel facade; the custom `__callStatic` was removed.
- Bulk sending uses a provider's native batch endpoint where available; records are
  written per recipient with the batch's aggregate status.

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
