# SMS User Segments — Design

Date: 2026-07-01
Package: `rayzenai/laravel-sms`
Release: minor, additive (new table + resource + one new send mode; nothing breaking)

## Goal

Let an admin define a **saved, named query** over the app's user table and send bulk
SMS to everyone it matches. Queries are saved; **results are never stored** — the
match set is recomputed live every time, so it is always fresh.

The admin **types** a field name and a value, **picks** an operator from a dropdown,
**picks** AND/OR, and **groups** conditions with `( )` (arbitrary nesting).

## Safety model ("free-typed, still injection-safe")

Field names and values are free-typed in the UI, but nothing arbitrary reaches SQL:

- **Field names** are only allowed if they are a real column on the configured user
  table, checked against the live schema (`Schema::getColumnListing()`). An unknown
  column fails validation with "no such field" — never interpolated.
- **Operators** come from a fixed allowlist (below); anything else is rejected.
- **Values** always go through query bindings — never string-interpolated.

The evaluator (`SegmentQuery`) is the single chokepoint that turns a tree into SQL.
The Filament layer never builds SQL itself.

## Data model — `sms_segments`

| column           | type                     | purpose                          |
|------------------|--------------------------|----------------------------------|
| `id`             | pk                       |                                  |
| `name`           | string                   | e.g. "Active Nepal users"        |
| `conditions`     | json                     | the condition tree (below)       |
| `previous_count` | unsigned int, nullable   | match count at last use          |
| `last_used_at`   | timestamp, nullable      | shown in the list                |
| `timestamps`     |                          |                                  |

(Package migration uses `json`, not `jsonb` — the package is portable across MySQL
and Postgres. The jsonb rule applies to first-party apps, not distributed packages.)

## Condition tree (stored in `conditions`)

Recursive group tree. A `( )` group in the UI maps 1:1 to a group node.

```json
{ "logic": "and", "children": [
    { "logic": "or", "children": [
        { "field": "country", "op": "=", "value": "Nepal" },
        { "field": "country", "op": "=", "value": "India" }
    ]},
    { "field": "is_active", "op": "=", "value": true }
]}
```

- **Group** = `{ "logic": "and"|"or", "children": [...] }` → renders as `( ... )`.
- **Condition** = `{ "field": string, "op": string, "value": mixed }`.
- Groups nest arbitrarily. `(A OR B) AND (C OR D)` is expressed exactly this way.
- Mixed logic like `A OR B AND C` is expressed by grouping — every group carries a
  single AND/OR for its direct children.

### Operators (fixed dropdown)

| op         | SQL                         | value input |
|------------|-----------------------------|-------------|
| `=`        | `col = ?`                   | shown       |
| `!=`       | `col != ?`                  | shown       |
| `>`        | `col > ?`                   | shown       |
| `>=`       | `col >= ?`                  | shown       |
| `<`        | `col < ?`                   | shown       |
| `<=`       | `col <= ?`                  | shown       |
| `contains` | `col LIKE %?%`              | shown       |
| `in`       | `col IN (?, ?, ...)`        | shown (comma-separated → bound array) |
| `is_set`   | `col IS NOT NULL`           | hidden      |
| `is_empty` | `col IS NULL`               | hidden      |

## Evaluator — `SegmentQuery`

Walks the tree and builds an Eloquent query using **nested closures**: each group
becomes `$q->where(function ($q) { ... }, boolean: and|or)`; each condition applies
its operator with bindings. Public surface:

- `count(): int` — for the live preview and `previous_count`.
- `users(): Collection` — the recipients at send time.

Every `field` is validated against the user table's columns; every `value` is bound.

## Filament — `SmsSegmentResource`

- **List:** `name`, `previous_count`, `last_used_at`.
- **Create/Edit:** a schema `Builder` with two block types:
  - **Condition** — field text input · operator select · value input (value hidden
    for `is_set` / `is_empty`).
  - **Group** — an AND/OR toggle + a nested `Builder` (its own children).
  - A **"Preview matches"** action runs `count()` and shows the live number before
    saving.

## Send flow

On the existing **Send SMS** create screen, add a **"Send to segment"** mode
alongside manual numbers and user selection:

1. Pick a saved segment → show its current live count.
2. Send resolves `SegmentQuery::users()` → each user's `smsPhoneNumber()`, reusing
   the existing bulk path (null numbers skipped).
3. After sending, stamp `last_used_at = now()` and `previous_count = <count>`.

## Tests (Pest)

`SegmentQuery` coverage: each operator; nested AND/OR precedence; unknown-field
rejection; `in` splitting and null handling (`is_set`/`is_empty`); an injection
attempt via a crafted field name that fails safely (no such column).

## Config

No new allowlist config — the queryable fields are "any real column on the
`user_model.class` table". Reuses the existing `user_model` config block for the
target model and its phone field.

## Rollout

1. Ship as a minor package release (e.g. `6.1.0`), GitHub release → Packagist.
2. Bump `rayzenai/laravel-sms` in `healthy-home/api`, push to `staging`.
