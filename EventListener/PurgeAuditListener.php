<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\EventListener;

use Jul6Art\AuditBundle\Service\AuditLogger;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;

/**
 * Records an `entity.purged` row for every line `core:purge` removes.
 *
 * The purge command deliberately writes no journal of its own — it dispatches one
 * {@see EntityPurgedEvent} per removed row and leaves the tracing to whoever cares. That is
 * what let it live in `jul6art/core-bundle` without dragging an audit trail along, and this
 * listener is the other half of the contract.
 *
 * It matters more than it looks: retention deletes rows for good, and the trail is the only
 * remaining evidence that they existed. The row names the entity, its id, its organisation and
 * the interval that condemned it.
 *
 * Registered **only when both halves are present** — `symfony/console` and `symfony/lock` for
 * the command, and a `core-bundle` recent enough to dispatch the event. Nothing to configure.
 */
final class PurgeAuditListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        /** @var class-string */
        private readonly string $logClass = '',
    ) {
    }

    public function onEntityPurged(EntityPurgedEvent $event): void
    {
        // ⚠️ Purging the TRAIL itself is not logged: each purged row would write a fresh
        // `entity.purged` row, the table would never shrink, and the retention policy would only
        // rotate it. The row count printed by `core:purge` remains the evidence of that purge.
        if ('' !== $this->logClass && is_a($event->getEntityClass(), $this->logClass, true)) {
            return;
        }

        $this->auditLogger->log(
            'entity.purged',
            $event->getOrganizationId(),
            null,
            $event->getEntityShortName(),
            $event->getEntityId(),
            ['reason' => 'Retention policy: '.$event->getInterval()],
        );
    }
}
