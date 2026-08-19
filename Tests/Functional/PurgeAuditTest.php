<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\AuditLog;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\Invoice;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Le contrat entre `core:purge` et la piste d'audit.
 *
 * La commande de purge n'écrit volontairement aucun journal : elle diffuse un
 * {@see EntityPurgedEvent} par ligne supprimée et laisse la trace à qui la veut. C'est ce qui lui
 * a permis de vivre dans core-bundle sans y traîner un `AuditLogger`. Ce test vérifie l'autre
 * moitié du contrat — et il compte, parce que la rétention supprime définitivement : la ligne
 * d'audit est la seule preuve restante que la donnée a existé.
 */
#[CoversNothing]
final class PurgeAuditTest extends AbstractFunctionalTestCase
{
    public function testAPurgedRowBecomesAnAuditEntry(): void
    {
        $container = $this->boot('test', ['log_class' => AuditLog::class], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->dispatch(new EntityPurgedEvent(
            entityClass: Invoice::class,
            entityShortName: 'Invoice',
            entityId: 4242,
            organizationId: 7,
            interval: '-3 months',
        ), EntityPurgedEvent::NAME);

        $rows = array_values($entityManager->getRepository(AuditLog::class)->findAll());
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame('entity.purged', $row->getAction());
        self::assertSame('Invoice', $row->getTargetType());
        self::assertSame('4242', $row->getTargetId());
        self::assertSame(7, $row->getOrganizationId());
        self::assertNull($row->getUserId(), 'Une purge planifiée n\'a pas d\'acteur humain.');
        self::assertSame(
            ['reason' => 'Retention policy: -3 months'],
            $row->getPayload(),
            "L'intervalle qui a condamné la ligne doit rester lisible.",
        );
    }
}
