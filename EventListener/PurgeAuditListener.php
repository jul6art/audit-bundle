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
    ) {
    }

    public function onEntityPurged(EntityPurgedEvent $event): void
    {
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
