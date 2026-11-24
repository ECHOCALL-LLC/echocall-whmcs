# EchoCall module for WHMCS

[![CI](https://github.com/ECHOCALL-LLC/echocall-whmcs/actions/workflows/ci.yml/badge.svg)](https://github.com/ECHOCALL-LLC/echocall-whmcs/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/ECHOCALL-LLC/echocall-whmcs)](https://github.com/ECHOCALL-LLC/echocall-whmcs/releases/latest)
[![License: Apache-2.0](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)
![WHMCS 8.13 LTS, 9.0, 9.1](https://img.shields.io/badge/WHMCS-8.13%20LTS%20%7C%209.0%20%7C%209.1-65a30d)
![PHP 8.2 to 8.4](https://img.shields.io/badge/PHP-8.2%20to%208.4-777bb4)

Version 2.0.0

Sell AI voice agents and chat agents from your WHMCS installation. The module creates
the customer account on EchoCall when an order is accepted, keeps suspension,
unsuspension, package changes and termination in sync, and gives your clients a
client area to run their agents, chatbots and phone numbers without leaving WHMCS.

It talks to the EchoCall REST API (v1, the same API that is documented in the OpenAPI
reference) with your partner API key. Every customer call is made on behalf of the
linked customer, so a client only ever sees their own data.

## Requirements

| | Supported |
| --- | --- |
| WHMCS | 8.13 (LTS), 9.0, 9.1 |
| PHP | 8.2, 8.3, 8.4 |
| PHP extensions | curl, json, mbstring (all part of a standard WHMCS server) |
| Network | Outbound HTTPS to `hub.echocall.de` on port 443 |
| EchoCall | A partner (reseller) account and an API key of that account |
| Top ups | Clients whose WHMCS currency is EUR |

WHMCS 8.12 and older and PHP 8.1 and older are not supported.

## Install

1. Download [echocall-whmcs.zip](https://github.com/ECHOCALL-LLC/echocall-whmcs/releases/latest/download/echocall-whmcs.zip) from the latest release.
2. Extract it into the root folder of your WHMCS installation. It holds `modules/servers/echocall/`.
3. Follow [INSTALL.md](INSTALL.md) to create the product.

The release ZIP also contains the WordPress plugin for your clients. A copy of `modules/servers/echocall/` straight from this repository works as well; its WordPress page then links the latest plugin release from [echocall-wordpress](https://github.com/ECHOCALL-LLC/echocall-wordpress).

## What your clients get

The module adds its own pages to the product details page of the service:

- **Overview**: balance, low balance notice, top up by invoice, package and what is
  left of it, usage this month, counts of agents, chatbots and numbers, recent top ups.
- **Voice agents**: pause and activate, open the knowledge of an agent.
- **Chatbots**: pause and activate, copy the embed code, open the knowledge.
- **Phone numbers**: choose which voice agent answers a number, give a number a label.
- **Knowledge**: add a web page as a source, see whether it is ready, remove a source.
- **Integrations**: which integrations are connected.
- **WordPress**: download of the EchoCall chat widget plugin, how to connect it with an
  API key, and the chatbots of the client with a shortcode for each. The plugin needs
  WordPress 6.8 and PHP 7.4 or later.

The pages follow the language of the client (German, English, French) and the light or
dark look of your WHMCS theme. Anything that needs the full dashboard opens your portal
in a new window.

## What you get as the partner

- Automatic provisioning: Create, Suspend, Unsuspend, Terminate, Change Package.
- On the service in the admin area: EchoCall customer id, balance, usage this month with
  revenue and margin, a link to the portal and a field to credit the customer.
- Buttons: **Test Connection**, **Sync Status** (brings EchoCall in line with the status of
  the service in WHMCS) and **Relink By Email** (links a service to an existing EchoCall
  customer of your partner account with the same email address).
- Start credit for new customers and top ups by WHMCS invoice, both in EUR.
- The merge field `{$echocall_portal_url}` for your product emails.
- Failures in the WHMCS activity log, successful provisioning steps too if you like.

## Product settings

The settings keep their positions from version 1, so existing products keep working.

| # | Setting | Meaning |
| --- | --- | --- |
| 1 | API Key | API key of your partner account, starts with `eck_live_`. Stored encrypted by WHMCS. |
| 2 | API Base URL | Leave empty for `https://hub.echocall.de/api/v1`. |
| 3 | Default Language | Language of new customers when the client has none set: de, en or fr. |
| 4 | Welcome Email | Sends the EchoCall welcome email with the sign in link to new customers. |
| 5 | Activity Log | Also notes successful provisioning steps in the WHMCS activity log. Failures are always noted. |
| 6 | Plan | Package of new customers. Save the API key first, then reload to fill the list. Empty means pay as you go. |
| 7 | Portal Login URL | Your own sign in address. Empty uses the portal of your partner account. Must start with https. |
| 8 | Upgrade Link | Shows a link to the WHMCS upgrade page in the client area. |
| 9 | Display Theme | auto follows your WHMCS theme, light and dark force one look. |
| 10 | Start Credit (EUR) | Credit booked once when a new customer is created. Empty or 0 for none. |
| 11 | Top Up Amounts (EUR) | Comma separated net amounts clients can top up, for example `10,25,50,100`. Empty turns top ups off. |

## Top ups

1. The client picks an amount on the overview page.
2. The module creates a WHMCS invoice for that amount. The amount is net, WHMCS adds tax
   according to your tax settings. An open invoice for the same amount is reused.
3. When the invoice is paid, the amount is credited on EchoCall exactly once. The booking
   carries the text `Top up, WHMCS invoice #<id>`, which is how a retry recognises a booking
   that already went through.
4. If EchoCall cannot be reached at that moment, the daily WHMCS cron tries again.
5. A cancelled invoice closes the top up.

Top ups are offered to clients whose WHMCS currency is EUR only.

## Suspension

The module suspends and unsuspends the EchoCall customer when WHMCS does. It never lifts
a suspension on its own, so a suspension you set for another reason stays in place.
To unsuspend automatically after an overdue invoice is paid, turn on **Enable Unsuspension**
in **Configuration > System Settings > Automation Settings**.

## Security

- The API key is a password setting and stored encrypted by WHMCS. It is masked in the
  module log and never sent to the browser.
- Requests go over HTTPS only (plain http is accepted for localhost during development).
- Every client action needs a random token tied to the WHMCS session and to the service,
  and the service must be active. Ids and URLs from the browser are checked before they
  reach the API.
- Every value on the client area pages is escaped. The pages need no inline scripts.
- Customer calls carry the linked customer id, the API rejects customers of other partners.

## Upgrading from version 1

1. Replace the folder `modules/servers/echocall` completely. Version 2 removed the folders
   `modules` and `templates/admin` and several files, a copy over the old folder leaves
   them behind.
2. Your product settings stay. Setting 5 was called Debug and is now Activity Log.
3. The custom field `EchoCall Service ID` stays the link between a service and its
   EchoCall customer. For a service without a link, use **Relink By Email**.
4. Clear the template cache under **Utilities > System > System Cleanup**.

See [INSTALL.md](INSTALL.md) for a first installation.

## Files

```
modules/servers/echocall/
  echocall.php           module functions
  hooks.php              top ups, daily retry, email merge field
  lib/                   API client, settings, client area, top ups, languages
  lang/                  german.php, english.php, french.php
  templates/clientarea/  main.tpl
  assets/                echocall.css, echocall.js, logo
  wordpress-plugin/      echocall-chat-widget.zip for your clients (release ZIP only)
  LICENSE                Apache License 2.0 (release ZIP only)
```

The repository keeps the module under the same path, so the release ZIP is that folder plus the bundled WordPress plugin and the license. `.github/` holds CI, the release workflow and the scripts both use.

## Development

No build step and no dependencies. With PHP 8.2 or later on the path:

```sh
sh .github/scripts/lint-php.sh        # syntax check of every PHP file
sh .github/scripts/check-versions.sh  # every version number agrees
sh .github/scripts/build-zip.sh       # build/echocall-whmcs.zip from HEAD
```

`build-zip.sh path/to/echocall-chat-widget.zip` bundles a WordPress plugin ZIP as well. CI runs the syntax check on PHP 8.2, 8.3 and 8.4, checks the versions and inspects the ZIP.

### Releasing

1. Raise the version everywhere `check-versions.sh` looks and add a section to `CHANGELOG.md`.
2. Commit and push to `main`.
3. Tag the commit, for example `git tag v2.0.1 && git push origin v2.0.1`.

The release workflow checks that tag and versions agree, downloads the latest release of the WordPress plugin, builds `echocall-whmcs.zip` with it, publishes the release with the changelog section as notes and records the release as live. The asset name never changes, so the download link above always serves the newest version.

## Security reports

Please report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## License

Apache License 2.0, see [LICENSE](LICENSE). Copyright 2026 EchoCall LLC.
