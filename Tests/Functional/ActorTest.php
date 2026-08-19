<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AuditBundle\Service\ActorResolver;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\AuditLog;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\Invoice;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Qui a agi, et pour le compte de qui.
 *
 * C'est la question à laquelle une piste d'audit doit répondre sans ambiguïté, et le seul endroit
 * où elle peut mentir : pendant une usurpation d'identité, le compte courant est le compte
 * **usurpé**. S'arrêter là écrit « l'utilisateur a fait ceci » là où un administrateur l'a fait
 * en son nom.
 */
#[CoversNothing]
final class ActorTest extends AbstractFunctionalTestCase
{
    public function testTheAuthenticatedUserIsRecordedAsTheActor(): void
    {
        $container = $this->bootWithSchema();
        $this->authenticate($container, new UserWithId(3));

        $this->persist($container, new Invoice('INV-001'));

        $row = $this->onlyRow($container);
        self::assertSame(3, $row->getUserId());
        self::assertNull($row->getImpersonatorId(), 'Une connexion réelle n\'a pas d\'usurpateur.');
    }

    public function testAnImpersonationRecordsBothIdentities(): void
    {
        $container = $this->bootWithSchema();
        $tokenStorage = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);

        $impersonated = new UserWithId(3);
        $admin = new UserWithId(1);
        $tokenStorage->setToken(new SwitchUserToken(
            $impersonated,
            'main',
            $impersonated->getRoles(),
            new UsernamePasswordToken($admin, 'main', $admin->getRoles()),
        ));

        $this->persist($container, new Invoice('INV-001'));

        $row = $this->onlyRow($container);
        self::assertSame(3, $row->getUserId(), 'Le compte au nom duquel on agit.');
        self::assertSame(1, $row->getImpersonatorId(), "L'administrateur qui agit réellement.");
    }

    /**
     * Un utilisateur sans identifiant — `UserInterface` n'en déclare aucun — ne doit pas faire
     * échouer l'écriture : la ligne est écrite sans acteur.
     */
    public function testAUserWithoutAnIdentifierYieldsNoActor(): void
    {
        $container = $this->bootWithSchema();
        $this->authenticate($container, new InMemoryUser('bob', null));

        $this->persist($container, new Invoice('INV-001'));

        self::assertNull($this->onlyRow($container)->getUserId());
    }

    /**
     * Le résolveur est enregistré parce que la sécurité est là. Sans elle, il disparaît et les
     * deux consommateurs reçoivent `null` — vérifié par le test de conteneur.
     */
    public function testTheResolverIsWiredWhenSecurityIsPresent(): void
    {
        self::assertInstanceOf(ActorResolver::class, $this->bootWithSchema()->get(ActorResolver::class));
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function bootWithSchema(): ContainerInterface
    {
        $container = $this->boot('test', ['log_class' => AuditLog::class], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $container;
    }

    private function authenticate(
        ContainerInterface $container,
        UserInterface $user,
    ): void {
        $tokenStorage = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function persist(ContainerInterface $container, object $entity): void
    {
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    private function onlyRow(ContainerInterface $container): AuditLog
    {
        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $rows = array_values($entityManager->getRepository(AuditLog::class)->findAll());
        self::assertCount(1, $rows);

        return $rows[0];
    }
}
