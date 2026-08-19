<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Service;

use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;

/**
 * Answers "who is doing this, and on whose behalf" — once, for the whole bundle.
 *
 * All of the logic lives in `jul6art/core-bundle`'s {@see TokenStorageAwareTrait}; this class
 * exists so that the trait is wired in exactly one place, and so that the audit stack has a
 * single dependency to make optional. It is registered **only when `symfony/security-bundle`
 * provides a token storage**: without it, `AuditLogger` and `AuditableListener` receive `null`
 * and every row is recorded with no actor — which is the correct answer for a CLI command or a
 * consumer running outside any session.
 *
 * @see AuditLogger
 * @see \Jul6Art\AuditBundle\EventListener\AuditableListener
 */
final class ActorResolver
{
    use TokenStorageAwareTrait;
}
