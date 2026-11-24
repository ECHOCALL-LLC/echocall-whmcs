# Security policy

## Supported versions

Only the latest release receives fixes. Please update before you report a problem.

| Version | Supported |
| ------- | --------- |
| 2.x     | Yes       |
| 1.x     | No        |

## Reporting a vulnerability

Please do not open a public issue for a security problem. Write to **team@echocall.de** or use [private vulnerability reporting](https://github.com/ECHOCALL-LLC/echocall-whmcs/security/advisories/new) on GitHub.

Include the module version, the WHMCS and PHP versions, the steps that show the problem and what an attacker could do with it. We confirm receipt and keep you posted until a fix is released.

## How the module handles secrets

- The partner API key is a password setting, stored encrypted by WHMCS, masked in the module log and never sent to the browser.
- Requests go over HTTPS only. Plain http is accepted for localhost during development.
- Every client action needs a random token tied to the WHMCS session and to the service, and the service must be active.
- Customer calls carry the linked customer id, the API rejects customers of other partners.
