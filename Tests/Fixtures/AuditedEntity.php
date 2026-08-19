<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures;

use Jul6Art\AuditBundle\Attribute\Auditable;

/**
 * Carries the attribute with explicit arguments — the shape an entity uses when the defaults do
 * not fit.
 */
#[Auditable(onUpdate: false, ignoredFields: ['lastSeenAt'])]
class AuditedEntity
{
}
