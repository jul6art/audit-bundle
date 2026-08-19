<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

<p align="center">
    <a href="https://opensource.org/licenses/MIT" target="_blank"><img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License"></a>
    <img src="https://img.shields.io/static/v1?label=stable&message=v2&color=orange" alt="Version">
</p>

jul6art/audit-bundle
====================
Symfony audit bundle
--------------------

> :warning: Work in progress so keep calm. The good news: this is maintained!

Requirements
------------

* **php ^8.5**
* **symfony ^7.4 || ^8.0**
* **jul6art/core-bundle ^2.0**

Installation
------------

```shell
composer require jul6art/audit-bundle
```

Setting it up
-------------

The bundle records **who changed what, when, and on whose behalf** — one row per write, in a
table you own. Three steps, and the third is the one people forget.

### 1. Declare the entity that holds the trail

The bundle ships `Entity\AuditLog` as a **mapped superclass**, not an entity. That is deliberate:
the table name, the indexes, and whatever you expose on top — an API resource, a retention
policy, a role check — belong to your application, not to a bundle.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Attribute\Purgeable;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['action'])]
#[ORM\Index(columns: ['created_at'])]
#[Purgeable(field: 'createdAt', interval: '-3 months')]
class AuditLog extends \Jul6Art\AuditBundle\Entity\AuditLog
{
    use IdTrait;
}
```

> ⚠️ **Bring your own identifier.** A mapped superclass may not declare the primary key of its
> children, so the concrete class does — `IdTrait` from jul6art/core-bundle, or a hand-written
> `$id`. Forgetting it fails at mapping time with a clear message, which is the good kind of
> failure.

Then generate the migration: `bin/console doctrine:migrations:diff`. The columns are
`organization_id`, `user_id`, `impersonator_id`, `action`, `target_type`, `target_id`, `payload`,
`diff`, `ip_address`, `user_agent`, `created_at`.

Add indexes for the way *you* read the trail. The two above are the minimum for a chronological
screen filtered by action; add `(target_type, target_id)` for a per-object history, `user_id` for
a per-actor listing, `organization_id` for a multi-tenant scope.

### 2. Point the bundle at it

```yaml
# config/packages/audit.yaml
audit:
    log_class: App\Entity\AuditLog
```

**This option has no default and the bundle refuses to boot without it.** A mapped superclass has
no table, so a missing or wrong `log_class` would leave every `#[Auditable]` silently inoperative
— an audit trail that records nothing, and says nothing about it. Failing at container build is
the lesser evil.

### 3. Annotate what you want audited

```php
use Jul6Art\AuditBundle\Attribute\Auditable;

#[ORM\Entity]
#[Auditable]
class Invoice { … }

#[Auditable(onUpdate: false)]                              // creations and deletions only
#[Auditable(ignoredFields: ['updatedAt', 'viewCount'])]    // noise kept out of the diff
class Draft { … }
```

That is all. `EventListener\AuditableListener` is registered on Doctrine's `postPersist`,
`postUpdate` and `preRemove` as soon as `audit.enabled` is true, and it is what actually reads the
attribute.

What a row says
---------------

| Column | Filled with |
| --- | --- |
| `action` | `<entity>.<event>` in lower case — `invoice.created`, `user.updated` |
| `target_type` / `target_id` | the entity's short class name, and its id as a string (so a UUID fits) |
| `organization_id` | `getOrganization()->getId()` or `getOrganizationId()`, when either exists |
| `user_id` | the account the request runs as |
| `impersonator_id` | the account that started an impersonation, `null` on a genuine login |
| `payload` | `{"diff": {"field": {"old": …, "new": …}}}` on an update, your own array on a manual entry |
| `ip_address` / `user_agent` | from the current request, `null` in a CLI command |

Diff values are flattened so the row stays readable and serialisable: a date becomes ISO 8601, an
enum its backing value, a related entity its id.

### A soft delete reads as a deletion, not an update

A soft delete is technically an `UPDATE` of `deletedAt`, and logging it as `updated` produces a
trail nobody can read. When the change set shows `deletedAt` going from `null` to a date, the row
is recorded as `deleted` — and as `restored` on the way back.

> ⚠️ **So do not log those actions by hand.** An application that also calls
> `$auditLogger->log('invoice.deleted')` in its service ends up with two rows for one event.

### Who is acting, and on whose behalf

The actor comes from the security token, through jul6art/core-bundle's
`TokenStorageAwareTrait`. Two things follow:

- **No security bundle, no actor.** `symfony/security-bundle` is a `suggest`, not a requirement:
  without it every row is written with a null actor, which is the correct answer for a CLI command
  and an honest one for an application with no security layer.
- **An impersonation is recorded twice over.** `user_id` is the impersonated account —
  what most code wants — and `impersonator_id` is the administrator who started the switch.
  Recording only the first would say the user did what an administrator did in their name.

Writing an entry yourself
-------------------------

The attribute covers entity writes. Everything else — an email sent, an export downloaded, a
permission granted — is a call:

```php
$this->auditLogger->log('invoice.sent', $organizationId, $actorId, 'Invoice', $invoice->getId(), ['channel' => 'email']);
```

### Batching a fan-out

`log()` flushes immediately, which is right for one action and disastrous for a hundred: each
flush re-runs Doctrine's listener cascade over everything accumulated so far, so a ~50-query
request becomes 1500+.

```php
$this->auditLogger->startBatch();
try {
    $this->provisionEverything();
} finally {
    $this->auditLogger->endBatch();   // one flush, here
}
```

Calls nest, and only the outermost `endBatch()` flushes.

> ⚠️ **Always close a batch in a `finally`.** An exception escaping an open batch leaves the
> logger buffering every subsequent row until something else flushes — the trail then looks
> incomplete rather than broken, which is harder to notice.

### Silencing the listener during a fan-out

Batching the writes is not the same as not writing them. When a flow already records its intent
(`feature.assigned`) and the per-row trail underneath is noise:

```php
$this->auditableListener->startSkip();
try {
    $this->provisionForPlan($plan);   // hundreds of audited entities
} finally {
    $this->auditableListener->endSkip();
}
```

> ⚠️ **Coarse on purpose, and `finally` is not optional.** A leaked depth silently disables
> auditing for the rest of the request. Use it when the caller records the intent itself — never
> to hide writes.

Retention
---------

Rows accumulate forever unless you say otherwise. Put `#[Purgeable]` from jul6art/core-bundle on
your concrete entity (see step 1) and `core:purge` applies it.

There is deliberately **no `audit.retention` option**: `#[Purgeable(interval: …)]` is an
attribute argument, so it must be a compile-time constant and cannot read a container parameter.
A configuration key would look like it worked and would not.

The bundle listens to `core-bundle`'s `EntityPurgedEvent` and writes one `entity.purged` row per
purged line, naming the entity, its id, its organisation and the interval that condemned it. That
matters more than it looks: retention deletes for good, and the trail is the only remaining
evidence the data existed.

Configuration
-------------

```yaml
# config/packages/audit.yaml
audit:
    # Required. Your concrete entity extending Jul6Art\AuditBundle\Entity\AuditLog.
    log_class: App\Entity\AuditLog

    # Registers the Doctrine listener. false leaves the bundle installed and inert —
    # and drops the DoctrineBundle requirement with it.
    enabled: true

    # Kept out of every update diff. An entity naming its own list in
    # #[Auditable(ignoredFields: …)] REPLACES this one rather than adding to it,
    # so repeat these two if you still want them out.
    ignored_fields: ['updatedAt', 'updatedBy']
```

`audit.enabled` is also exposed as a container parameter.

Quality assurance
-----------------

```shell
composer qa           # coding standards, Rector, static analysis and tests
composer test         # PHPUnit
composer phpstan      # PHPStan, level max
composer cs           # PHP-CS-Fixer, writes the fixes
composer rector       # Rector, writes the fixes
```

License
-------

The Audit Bundle is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

&copy; 2026 [jul6art](https://devinthehood.com)
