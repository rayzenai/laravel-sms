# Configurable multi-provider SMS (v6) + AakashSMS

**Date:** 2026-07-01
**Status:** Approved

## Problem

Provider selection in the package is broken and inextensible:

- Two competing "which provider" config keys. `laravel-sms.default` (`SMS_PROVIDER`,
  a name) is what the docs tell users to set, but nothing reads it. The service
  provider binding reads a *different* key, `laravel-sms.default_provider`
  (`SMS_PROVIDER_CLASS`, a fully-qualified class name). So `SMS_PROVIDER=swift` is a
  silent no-op.
- The `providers` registry (name → class + keys) is never consulted for selection.
- Each provider reaches into a different ad-hoc spot in global `config()`, so there
  is no uniform convention for "where do this provider's credentials live".
- `SmsService` stores the provider *class name* into the `provider` DB column.

Adding a new provider (AakashSMS) on top of this deepens the mess. Fix the selection
and config layer first, then add AakashSMS as the first clean example.

## Decisions

- **Driver-manager pattern.** A new `SmsManager` resolves the active provider by
  name from the `providers` registry and injects that provider's config array into
  its constructor.
- **Clean break, ship as v6.** No backward compatibility. `SMS_PROVIDER_CLASS` /
  `default_provider` are removed. `SMS_PROVIDER=<name>` is the only selector.
- **Balance/credit capability** via an optional `ReportsBalance` interface. No
  Filament UI change.

## Architecture

### `SmsManager` (`src/SmsManager.php`)
- `getDefaultDriver(): string` → `config('laravel-sms.default')`.
- `driver(?string $name = null): SmsProviderInterface` → resolve by name, cache per
  name. `null` = default.
- `resolve($name)`: read `config("laravel-sms.providers.$name")`; require a `class`
  that exists and implements `SmsProviderInterface`; merge shared `timeout` +
  `default_sender` (as `sender`) into the provider config; instantiate via the
  container (`makeWith(..., ['config' => $config])`) so providers stay mockable.
- Unknown/misconfigured name → `ProviderNotConfiguredException`.

### Providers
- `SmsProviderInterface` (`send`/`sendBulk` → array) unchanged.
- New `AbstractSmsProvider` base: constructor `__construct(array $config = [])`,
  stores `$config`, `timeout`, `sender`; `config($key, $default)` helper; a default
  `sendBulk()` that loops `send()` (so a new provider only *must* implement
  `send()`). Providers with native batch override `sendBulk()`.
- `ReportsBalance { public function balance(): array; }` — optional capability.
- `HttpProvider`, `SwiftSmsProvider` extend the base and keep native bulk.
  `TwilioProvider` extends the base and uses the default loop bulk.
- `AakashSmsProvider` (new):
  - normalize numbers to local 10-digit (strip `+977`/`977`).
  - single → `POST https://sms.aakashsms.com/sms/v3/send`, form-encoded,
    `auth_token` field + `to` + `text`; success = `error === false`, sid from
    `data.valid.0.id`.
  - bulk → `POST https://sms.aakashsms.com/sms/v4/send-user`, JSON `{to:[], text:[msg]}`,
    `auth-token` header; failed if HTTP fails or `errors` non-empty.
  - `balance()` → `GET https://sms.aakashsms.com/sms/v4/available-credit`,
    `auth-token` header → `{credit, response}`.

### Orchestration
- `SmsService` depends on `SmsManager`, keeps DB record + logging, stores the
  provider **name** in the `provider` column. Adds `provider(string $name): static`
  (pinned clone), `to()`, and `balance()`.
- `Sms` facade drops its custom `__callStatic`; all methods (`to`, `send`,
  `sendBulk`, `provider`, `balance`) live on `SmsService` and forward via the base
  Facade. `Sms::provider('aakash')->to(...)->message(...)->send()` works.
- Service provider binds `SmsManager` (singleton), `SmsProviderInterface` →
  `manager->driver()`, and `SmsService` ← manager.

### Config shape
`default` = provider name; every provider's keys live under its registry entry;
shared `timeout`/`default_sender` merged in by the manager. Removed:
`default_provider`, top-level `api_base_url`/`api_key` (folded into the `http`
entry). Env var *names* are unchanged.

## Adding a provider (the payoff)
1. Write a class extending `AbstractSmsProvider`, implement `send()` (override
   `sendBulk()` / implement `ReportsBalance` if supported).
2. Add a `providers.<name>` entry pointing at the class with its keys.
3. Set `SMS_PROVIDER=<name>`.

## Testing
- `Http::fake()` unit tests per provider incl. AakashSMS single/bulk/balance +
  `+977` normalization.
- `SmsManager` tests: resolve-by-name, cache, `driver()` override, unknown-name
  throw, unsupported-balance throw.
- Existing Nepali-validation and API-endpoint tests updated to the new config shape
  (`default` = name, `providers.<name>`) and `provider` column = name.
```
