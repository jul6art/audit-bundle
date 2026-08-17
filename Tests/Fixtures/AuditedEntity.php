<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures;

use Jul6Art\AuditBundle\Attribute\Auditable;

/**
 * Carries the attribute with an explicit event list.
 */
#[Auditable(events: ['created', 'edited'])]
class AuditedEntity
{
}
