<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Jul6Art\AuditBundle\Entity\AuditLog;
use Jul6Art\CoreBundle\Repository\AbstractRepository;

/**
 * Reading side of the trail, on top of the bundle's {@see AbstractRepository} (so `save()`,
 * `delete()`, `flush()` and `clear()` come for free).
 *
 * The application subclasses it for its concrete entity, exactly as it does for the entity
 * itself:
 *
 * ```php
 *
 * /** @extends AuditLogRepository<AuditLog> *\/
 * class AuditLogRepository extends \Jul6Art\AuditBundle\Repository\AuditLogRepository
 * {
 *     public function __construct(ManagerRegistry $registry)
 *     {
 *         parent::__construct($registry, AuditLog::class);
 *     }
 * }
 * ```
 *
 * Only one query lives here, the one that is genuinely generic. Everything a specific screen
 * needs — a per-target history, a per-actor listing — depends on the indexes that application
 * chose, and belongs in its subclass.
 *
 * @template TEntity of AuditLog
 *
 * @extends AbstractRepository<TEntity>
 */
abstract class AuditLogRepository extends AbstractRepository
{
    /**
     * How many times an organisation performed one action since a given moment.
     *
     * The use case is rate limiting on top of the trail rather than a second counter table:
     * "how many imports has this organisation run in the last hour?". Counting rows the audit
     * already writes means the limit can never drift from what actually happened.
     */
    public function countByOrganizationActionSince(
        int $organizationId,
        string $action,
        \DateTimeImmutable $since,
    ): int {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.organizationId = :org')
            ->andWhere('a.action = :action')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('org', $organizationId)
            ->setParameter('action', $action)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
