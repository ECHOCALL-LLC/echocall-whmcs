# Changelog

All notable changes to the EchoCall module for WHMCS are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the module uses [Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-11-24

Rebuilt on the current EchoCall REST API v1. Existing products keep their settings.

### Added

- Client area pages for voice agents, chatbots, phone numbers, knowledge, integrations and the WordPress plugin, besides the overview.
- Light and dark look that follows the WHMCS theme, or one look forced by the new setting Display Theme.
- German, English and French, following the language of the client.
- Start credit for new customers and top ups by WHMCS invoice in EUR. A paid invoice is credited exactly once, the daily cron retries a booking that could not be made.
- Admin buttons Test Connection, Sync Status and Relink By Email, and on the service the customer id, balance, usage with revenue and margin and a field to credit the customer.
- Merge field `{$echocall_portal_url}` for product emails.
- Settings Portal Login URL, Upgrade Link, Display Theme, Start Credit and Top Up Amounts.
- The phone number of the client is passed on when the customer is created.
- Public source code on GitHub. A release ZIP that is extracted into the WHMCS root and bundles the latest WordPress plugin.

### Changed

- Requires WHMCS 8.13 LTS, 9.0 or 9.1 on PHP 8.2 to 8.4.
- Setting 5 is now called Activity Log and also notes successful provisioning steps if you like. Failures are always noted.
- The module never lifts a suspension on its own. Turn on Enable Unsuspension in WHMCS for that.
- Every client action needs a token tied to the WHMCS session and the service. Requests go over HTTPS only.

### Removed

- The folders `modules` and `templates/admin` and several old files. Replace the module folder completely when you upgrade.

## [1.0.0] - 2026-04-30

### Added

- First version.

[2.0.0]: https://github.com/ECHOCALL-LLC/echocall-whmcs/releases/tag/v2.0.0
