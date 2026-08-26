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
 *     $this->auditLogger->endBatch();   // one persist + one flush, here
 * }
 * ```
 *
 * > ⚠️ **Always close a batch in a `finally`.** Calls nest, and only the outermost
 * > `endBatch()` flushes; an exception escaping an open batch leaves the logger buffering every
 * > subsequent row until something else flushes — the trail then looks incomplete rather than
 * > broken, which is worse.
 *
 * ## Why a batch buffers the rows instead of persisting them
 *
 * ⚠️ The obvious implementation — `persist()` now, flush at `endBatch()` — **loses every row
 * written from inside a flush**, which is where {@see \Jul6Art\AuditBundle\EventListener\AuditableListener}
 * always writes: `postPersist` and `postUpdate` fire *during* `UnitOfWork::commit()`, and the
 * commit ends by clearing `entityInsertions` wholesale. An entity persisted from a listener is
 * swept away with it, and the later flush has nothing left to insert. No exception, no warning:
 * the trail is simply empty.
 *
 * So a batch holds the instances itself and persists them once the window closes — by then the
 * commit is over and the insertions survive. This is also why an unbatched `log()` flushes
 * immediately rather than waiting: a re-entrant flush runs its own complete commit, which does
 * insert the row.
 *
 * Measured on wovex, 2026-08-26: a state-machine transition wrapped in a batch persisted its
 * `workorder.updated` row on every request and stored **none of them** — and the query budget
 * looked excellent, precisely because nothing was being written.
 *
 * Batching the writes is not the same thing as silencing the automatic listener: see
 * {@see \Jul6Art\AuditBundle\EventListener\AuditableListener::startSkip()} for that.
 */
class AuditLogger
{
    /**
     * Depth of the current `startBatch()` / `endBatch()` window. Above zero, `log()` buffers
     * instead of writing.
     */
    private int $batchDepth = 0;

    /**
     * Rows staged by an open batch, persisted by the matching `endBatch()`.
     *
     * @var list<AuditLog>
     */
    private array $buffer = [];

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
     * Closes the matching window, persists everything it staged and flushes once. A no-op while
     * an outer batch is still open.
     */
    public function endBatch(): void
    {
        if ($this->batchDepth > 0) {
            --$this->batchDepth;
        }

        if (0 !== $this->batchDepth) {
            return;
        }

        $buffered = $this->buffer;
        $this->buffer = [];

        foreach ($buffered as $auditLog) {
            $this->entityManager->persist($auditLog);
        }

        $this->entityManager->flush();
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

        if ($this->batchDepth > 0) {
            $this->buffer[] = $auditLog;

            return;
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }
}
