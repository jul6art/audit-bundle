<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AuditBundle\Entity\AuditLog;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes rows into the audit trail. Everything else in this bundle goes through it.
 *
 * ```php
 * $this->auditLogger->log('invoice.sent', $organizationId, $actorId, 'Invoice', $invoice->getId());
 * ```
 *
 * Two things it fills in on its own, so no caller has to remember them: the request context
 * (client IP and user agent, `null` outside a request — a CLI command) and the impersonator,
 * read from the current `SwitchUserToken`. See {@see ActorResolver}.
 *
 * ## Batching
 *
 * `log()` flushes immediately, which is what you want for a single action and disastrous for a
 * fan-out. A flow that persists a hundred audited entities in one request pays for it twice:
 * each flush re-runs Doctrine's listener cascade over everything accumulated so far, so a
 * ~50-query request becomes 1500+.
 *
 * ```php
 * $this->auditLogger->startBatch();
 * try {
 *     // …persist many rows; log() only stages them
 * } finally {
 *     $this->auditLogger->endBatch();   // one flush, here
 * }
 * ```
 *
 * > ⚠️ **Always close a batch in a `finally`.** Calls nest, and only the outermost
 * > `endBatch()` flushes; an exception escaping an open batch leaves the logger buffering every
 * > subsequent row until something else flushes — the trail then looks incomplete rather than
 * > broken, which is worse.
 *
 * Batching the writes is not the same thing as silencing the automatic listener: see
 * {@see \Jul6Art\AuditBundle\EventListener\AuditableListener::startSkip()} for that.
 */
class AuditLogger
{
    /**
     * Depth of the current `startBatch()` / `endBatch()` window. Above zero, `log()` persists
     * without flushing.
     */
    private int $batchDepth = 0;

    /**
     * @param class-string<AuditLog> $logClass concrete entity extending the bundle's mapped
     *                                         superclass — the application owns it, the bundle
     *                                         only instantiates it
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly string $logClass,
        private readonly ?ActorResolver $actorResolver = null,
    ) {
    }

    /**
     * Opens a batch window. Stackable.
     */
    public function startBatch(): void
    {
        ++$this->batchDepth;
    }

    /**
     * Closes the matching window and flushes. A no-op while an outer batch is still open.
     */
    public function endBatch(): void
    {
        if ($this->batchDepth > 0) {
            --$this->batchDepth;
        }

        if (0 === $this->batchDepth) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function log(
        string $action,
        ?int $organizationId = null,
        ?int $userId = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?array $payload = null,
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new $this->logClass(
            action: $action,
            organizationId: $organizationId,
            userId: $userId,
            targetType: $targetType,
            targetId: null !== $targetId ? (string) $targetId : null,
            payload: $payload,
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            impersonatorId: $this->actorResolver?->getOriginalUserIdOrNull(),
        );

        $this->entityManager->persist($auditLog);

        if (0 === $this->batchDepth) {
            $this->entityManager->flush();
        }
    }
}
