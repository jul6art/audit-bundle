<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood"></a>
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

Usage
-----

Mark the classes you want audited with the `Auditable` attribute. Passing no
argument audits every event; passing a list restricts them.

```php
use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\CoreBundle\Event\AbstractEvent;

#[Auditable]
class Article
{
}

#[Auditable(events: [AbstractEvent::CREATED, AbstractEvent::EDITED])]
class Comment
{
}
```

Read it back through plain reflection:

```php
$attributes = new ReflectionClass(Comment::class)->getAttributes(Auditable::class);
$events = $attributes[0]->newInstance()->getEvents();
```

Configuration
-------------

```yaml
# config/packages/audit.yaml
audit:
    enabled: true
```

The option is exposed as the `audit.enabled` container parameter.

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
