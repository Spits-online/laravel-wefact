# Changelog

All notable changes to `laravel-wefact` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-10-09

A rewrite. The package is now `spits-online/laravel-wefact`, with one client for
WeFact and HostFact. See [UPGRADE.md](UPGRADE.md#from-1x-to-20) for every change
an app has to make.

### Added
- A `wefact.driver` config key (`WEFACT_DRIVER`): `wefact` or `hostfact`. Both
  APIs speak the same protocol, so switching is a matter of config.
- Typed, read-only data objects for debtors, quotes, invoices, lines, products,
  subscriptions and domains, with enums for statuses, periods and product types.
  Every object keeps the full answer in `$raw`.
- Lists that page themselves: `WeFact::debtors()->get()` and the others return a
  `LazyCollection` that fetches the next page as you iterate.
- Filters on lists: status (one or several), debtor and `modifiedSince`.
- `Line::create()` for quote and invoice lines. It writes amounts without a
  thousands separator and dates in the API's timezone.
- Quote and invoice lines: `->lines()->add()` and `->lines()->remove()`.
- `WeFact::quote($id)->accept(createInvoice: true)`, `->decline()` and
  `->archive()`, and `WeFact::invoice($id)->delete()`.
- `find()` and `findByCode()`, which return null for a record that doesn't exist.
- `WeFact::fake()`: an in-memory WeFact or HostFact for testing apps, with
  assertions such as `assertQuoteAccepted()` and `assertInvoiceLinesAdded()`.
- `WeFact::request()` for any call the package doesn't model yet.
- An exception per failure, all extending `WeFactException`: `AccessDenied` (a
  wrong key, an IP address that isn't whitelisted, or WeFact's rate-limit
  block), `NotFound`, `RequestFailed`, `ConnectionFailed`, `MissingConfiguration`,
  `UnsupportedFeature` and `InvalidLine`.
- A `timeout` config key (`WEFACT_TIMEOUT`, 10 seconds) and a `timezone` key.

### Changed
- **Breaking:** the package is renamed to `spits-online/laravel-wefact`, and the
  namespace from `Spits\WeFactApi` to `SpitsOnline\WeFact`.
- **Breaking:** the env keys are `WEFACT_DRIVER`, `WEFACT_URL` and `WEFACT_KEY`;
  `wefact.type` and `wefact.client` are gone.
- **Breaking:** entities are replaced by resources and data objects. Lists return
  data objects instead of arrays, and creating, changing and deleting go through
  `WeFact::quote($id)` and the other resources.
- **Breaking:** requires PHP 8.3+ and Laravel 12 or 13.
- WeFact's API address is `https://api.mijnwefact.nl/v2/`, the address WeFact has
  used since 2019. The old default `https://mijnwefact.nl/apiv2/api.php` is gone.
- The config merges with the app's `config/wefact.php` key by key, so an app
  states only what it changes. Publishing the config is no longer needed.

### Fixed
- An API error is never swallowed: HTTP errors, an answer that isn't JSON, and a
  refused IP address all throw a package exception that keeps the API's answer.
- Accepting a declined quote throws instead of reporting success. The API
  answers success, but leaves the quote declined.
- An update sends only the fields you pass. v1 resent every field whose first
  value was empty on every save.

### Removed
- **Breaking:** the HostFact entities this package didn't use (hosting, SSL,
  VPS, tickets, services, domain contacts), creditors, purchase invoices,
  groups and attachments. `WeFact::request()` reaches all of them.

## [1.0.2] - 2026-04-22

### Added
- Laravel 13 compatibility.

### Fixed
- `BaseEntity` constructor and the `HostFact` factory methods use explicit
  nullable types (PHP 8.4 deprecation).
- The `phpunit/phpunit` constraint (`12.5` → `^12.0`).

## [1.0.1] - 2025-09-03

### Added
- `HostFact::domain()`.

### Fixed
- `HasAttachments::addAttachement()` is renamed to `addAttachment()`.

## [1.0.0] - 2025-05-23

- Initial release.

[Unreleased]: https://github.com/Spits-online/laravel-wefact/compare/V2.0.0...HEAD
[2.0.0]: https://github.com/Spits-online/laravel-wefact/compare/V1.0.2...V2.0.0
[1.0.2]: https://github.com/Spits-online/laravel-wefact/compare/V1.0.1...V1.0.2
[1.0.1]: https://github.com/Spits-online/laravel-wefact/compare/V1.0.0...V1.0.1
[1.0.0]: https://github.com/Spits-online/laravel-wefact/releases/tag/V1.0.0
