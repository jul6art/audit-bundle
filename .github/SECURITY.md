# Security Policy

## Supported versions

`jul6art/audit-bundle` is installed by other applications through Composer, so a fix here
reaches them the moment they update. Only the current major line gets one.

| Version | Supported |
| --- | --- |
| `3.x` | ✅ |
| `2.x` | ❌ |
| `1.x` | ❌ |
| any older tag or fork | ❌ |

Support means security fixes on the latest release of that line — upgrade to it before
reporting, in case the problem is already gone.

## What is in scope

An audit trail is a security control: it is read after an incident, and it is trusted.
Defects that matter:

* **A trail an attacker can falsify or erase** — an entry writable through the audited
  entity, a listener that can be turned off from request input, or an entry whose actor can
  be chosen by the client.
* **A secret copied into the trail** — a password hash, an API token, key material or an
  encrypted column's plaintext appearing in a recorded diff, where it survives every later
  rotation.
* **The wrong actor recorded** — an impersonated session logged as the impersonated user
  alone, so the trail hides who really acted.
* **A silencing scope that leaks** — the listener staying off after an exception, so later
  changes go unrecorded.
* **An action that leaves no entry at all** where the attribute says it should, especially a
  soft delete recorded as an ordinary update.

Out of scope: vulnerabilities in Symfony, Doctrine, API Platform or any other third-party
package — report those to the project that owns the code, and they will reach you through
your own `composer update`. Also out of scope: an application that misconfigures this bundle
in a way the README warns against, though a warning that turns out to be easy to miss is
worth an issue of its own.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/audit-bundle/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the version of `jul6art/audit-bundle` and of Symfony you are running,
* the relevant part of your bundle configuration,
* the shortest reproduction you have — ideally a failing test against this
  repository, since that is what a fix will be built on,
* what an attacker gains: which check is bypassed, which data is read or
  written, and whether authentication is required.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix released on the supported line, a
  [security advisory](https://github.com/jul6art/audit-bundle/security/advisories)
  describing the impact and the version to upgrade to, and credit in it unless
  you ask otherwise.

Please give the maintainers a reasonable window to ship a release before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.