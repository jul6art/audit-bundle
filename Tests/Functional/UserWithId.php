<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * `UserInterface` ne déclare aucun identifiant : le résolveur lit `getId()` quand la classe en
 * expose un. Cette classe est le cas normal, `InMemoryUser` le cas limite.
 */
final readonly class UserWithId implements UserInterface
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'user-'.$this->id;
    }
}
