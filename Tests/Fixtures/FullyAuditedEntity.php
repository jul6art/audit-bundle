<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures;

use Jul6Art\AuditBundle\Attribute\Auditable;

/**
 * Carries the attribute without arguments, meaning every event is audited.
 */
#[Auditable]
class FullyAuditedEntity
{
}
