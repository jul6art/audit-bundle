<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuditBundle\EventListener\AuditableListener;
use Jul6Art\AuditBundle\Service\AuditLogger;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\AuditLog;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\Draft;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\Invoice;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\PlainRecord;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\Ticket;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The audit trail against a real in-memory SQLite database.
 *
 * Mocking Doctrine's unit of work would prove nothing here: what the bundle promises is that a
 * write **through the ORM** leaves a row behind, with the right action name and the right diff.
 * The change set only exists inside a real flush.
 *
 * This is also what stands in for the cross-database validation the plan expected from a second
 * consumer on MySQL (decision of 2026-08-19): a mapped superclass carries no DDL, so exercising
 * it on a second engine — here SQLite next to the project's PostgreSQL — is what catches a
 * mapping that only works in one place.
 */
#[CoversNothing]
final class AuditTrailTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    private AuditLogger $auditLogger;

    private AuditableListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot('test', ['log_class' => AuditLog::class], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $auditLogger = $container->get(AuditLogger::class);
        self::assertInstanceOf(AuditLogger::class, $auditLogger);
        $this->auditLogger = $auditLogger;

        $listener = $container->get(AuditableListener::class);
        self::assertInstanceOf(AuditableListener::class, $listener);
        $this->listener = $listener;

        new SchemaTool($this->entityManager)->createSchema(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
        );
    }

    // ── les trois événements ───────────────────────────────────────────────

    public function testCreatingAnAuditedEntityLeavesARow(): void
    {
        $invoice = $this->persist(new Invoice('INV-001', organizationId: 7));

        $row = $this->onlyRow();
        self::assertSame('invoice.created', $row->getAction());
        self::assertSame('Invoice', $row->getTargetType());
        self::assertSame((string) $invoice->getId(), $row->getTargetId());
        self::assertSame(7, $row->getOrganizationId(), "L'organisation dénormalisée doit être reprise.");
        self::assertNull($row->getPayload(), 'Une création n\'a pas de diff.');
    }

    public function testUpdatingLogsTheDiffOfTheChangedFieldsOnly(): void
    {
        $invoice = $this->persist(new Invoice('INV-001'));
        $this->clearTrail();

        $invoice->setAmount(4200);
        $this->entityManager->flush();

        $payload = $this->onlyRow('invoice.updated')->getPayload();
        self::assertIsArray($payload);
        self::assertArrayHasKey('diff', $payload);
        self::assertSame(['amount' => ['old' => 0, 'new' => 4200]], $payload['diff']);
    }

    public function testDeletingLogsARow(): void
    {
        $invoice = $this->persist(new Invoice('INV-001'));
        $this->clearTrail();

        $this->entityManager->remove($invoice);
        $this->entityManager->flush();

        self::assertSame('invoice.deleted', $this->onlyRow()->getAction());
    }

    public function testAnEntityWithoutTheAttributeIsIgnored(): void
    {
        $record = $this->persist(new PlainRecord());
        $record->setLabel('changed');
        $this->entityManager->flush();
        $this->entityManager->remove($record);
        $this->entityManager->flush();

        self::assertSame([], $this->rows(), 'Aucune ligne ne doit être écrite pour une entité non annotée.');
    }

    public function testOnUpdateFalseSilencesEditsButNotCreations(): void
    {
        $draft = $this->persist(new Draft('first'));
        self::assertSame('draft.created', $this->onlyRow()->getAction());
        $this->clearTrail();

        $draft->setBody('second');
        $this->entityManager->flush();

        self::assertSame([], $this->rows(), 'onUpdate: false doit faire taire la modification.');
    }

    // ── diff ──────────────────────────────────────────────────────────────

    public function testIgnoredFieldsAreExcludedFromTheDiff(): void
    {
        $ticket = $this->persist(new Ticket('Imprimante HS'));
        $this->clearTrail();

        $ticket->setSubject('Imprimante en panne')->view();
        $this->entityManager->flush();

        $payload = $this->onlyRow('ticket.updated')->getPayload();
        self::assertIsArray($payload);
        self::assertIsArray($payload['diff'] ?? null);
        self::assertSame(['subject'], array_keys($payload['diff']), 'viewCount et updatedAt sont exclus.');
    }

    /**
     * Une modification entièrement ignorée n'est pas une modification : écrire une ligne vide
     * ferait du bruit exactement là où l'`ignoredFields` cherchait à en retirer.
     */
    public function testAnUpdateTouchingOnlyIgnoredFieldsWritesNothing(): void
    {
        $ticket = $this->persist(new Ticket('Imprimante HS'));
        $this->clearTrail();

        $ticket->view();
        $this->entityManager->flush();

        self::assertSame([], $this->rows());
    }

    public function testTheDefaultIgnoredFieldsApplyToAnEntityThatNamesNone(): void
    {
        $invoice = $this->persist(new Invoice('INV-001'));
        $this->clearTrail();

        // `updatedAt` fait partie du défaut applicatif : seul `reference` doit apparaître.
        $invoice->setReference('INV-002')->touch();
        $this->entityManager->flush();

        $payload = $this->onlyRow('invoice.updated')->getPayload();
        self::assertIsArray($payload);
        self::assertIsArray($payload['diff'] ?? null);
        self::assertSame(['reference'], array_keys($payload['diff']));
    }

    // ── suppression douce ─────────────────────────────────────────────────

    /**
     * Une suppression douce est un UPDATE de `deletedAt`. La consigner comme « updated » rend la
     * piste illisible — c'est le cas que le listener nomme lui-même.
     */
    public function testASoftDeleteIsRecordedAsDeletedThenRestored(): void
    {
        $invoice = $this->persist(new Invoice('INV-001'));
        $this->clearTrail();

        $invoice->softDelete();
        $this->entityManager->flush();
        self::assertSame('invoice.deleted', $this->onlyRow()->getAction());
        $this->clearTrail();

        $invoice->restore();
        $this->entityManager->flush();
        self::assertSame('invoice.restored', $this->onlyRow()->getAction());
    }

    // ── acteur ────────────────────────────────────────────────────────────

    /**
     * Hors requête et hors session — une commande de console — l'acteur est `null`, et ce n'est
     * pas un défaut : c'est l'information exacte.
     */
    public function testTheActorIsNullOutsideAnySession(): void
    {
        $this->persist(new Invoice('INV-001'));

        $row = $this->onlyRow();
        self::assertNull($row->getUserId());
        self::assertNull($row->getImpersonatorId());
        self::assertNull($row->getIpAddress(), 'Aucune requête, donc aucune IP.');
    }

    // ── écriture manuelle et lots ─────────────────────────────────────────

    public function testAManualEntryIsWrittenAsIs(): void
    {
        $this->auditLogger->log('invoice.sent', 7, 3, 'Invoice', 42, ['channel' => 'email']);

        $row = $this->onlyRow();
        self::assertSame('invoice.sent', $row->getAction());
        self::assertSame(7, $row->getOrganizationId());
        self::assertSame(3, $row->getUserId());
        self::assertSame('42', $row->getTargetId(), "L'identifiant est stocké en chaîne, UUID compris.");
        self::assertSame(['channel' => 'email'], $row->getPayload());
    }

    /**
     * Ce que garantit le lot : rien n'est perdu, tout part en une fois. Le gain est le nombre de
     * `flush()`, donc de cascades de listeners — invisible dans une assertion, mais c'est la
     * raison d'être de la brique.
     */
    public function testABatchDefersTheFlushUntilItCloses(): void
    {
        $this->auditLogger->startBatch();
        $this->auditLogger->log('first');
        $this->auditLogger->log('second');

        self::assertSame([], $this->rowsFromDatabase(), 'Rien ne doit être en base avant la fermeture.');

        $this->auditLogger->endBatch();

        self::assertCount(2, $this->rowsFromDatabase());
    }

    public function testOnlyTheOutermostBatchFlushes(): void
    {
        $this->auditLogger->startBatch();
        $this->auditLogger->startBatch();
        $this->auditLogger->log('nested');
        $this->auditLogger->endBatch();

        self::assertSame([], $this->rowsFromDatabase(), 'Le lot intérieur ne ferme rien.');

        $this->auditLogger->endBatch();

        self::assertCount(1, $this->rowsFromDatabase());
    }

    public function testASkipWindowSilencesTheAutomaticListener(): void
    {
        $this->listener->startSkip();

        try {
            $this->persist(new Invoice('INV-001'));
        } finally {
            $this->listener->endSkip();
        }

        self::assertSame([], $this->rows());

        // Et la fenêtre refermée, l'audit reprend.
        $this->persist(new Invoice('INV-002'));
        self::assertCount(1, $this->rows());
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @return list<AuditLog>
     */
    private function rows(?string $action = null): array
    {
        $rows = $this->entityManager->getRepository(AuditLog::class)
            ->findBy(null !== $action ? ['action' => $action] : []);

        return array_values($rows);
    }

    /**
     * Contourne l'unit of work pour lire ce qui est **réellement** en base : c'est la seule façon
     * de distinguer « persisté » de « écrit » quand on teste un lot.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromDatabase(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->entityManager->getConnection()
            ->executeQuery('SELECT action FROM audit_log')
            ->fetchAllAssociative();

        return $rows;
    }

    private function onlyRow(?string $action = null): AuditLog
    {
        $rows = $this->rows($action);
        self::assertCount(1, $rows, \sprintf('Une seule ligne attendue, %d trouvée(s).', \count($rows)));

        return $rows[0];
    }

    /**
     * Vide la piste pour n'observer que ce que produit l'action suivante.
     *
     * Volontairement **sans** `clear()` : détacher l'unité de travail détacherait aussi l'entité
     * que le test s'apprête à modifier, et Doctrine ne verrait plus aucun changement — un test
     * qui passerait pour la mauvaise raison, ou qui refuserait le `remove()`.
     */
    private function clearTrail(): void
    {
        $this->entityManager->createQuery('DELETE FROM '.AuditLog::class)->execute();
    }
}
