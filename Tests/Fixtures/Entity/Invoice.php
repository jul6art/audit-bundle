<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Entity\Traits\SoftDeletableTrait;

/**
 * The ordinary case: audited on the three events, soft-deletable, and carrying a denormalised
 * organisation id — which is how the listener finds a tenant without knowing the application's
 * organisation class.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice')]
#[Auditable]
class Invoice
{
    use IdTrait;
    use SoftDeletableTrait;

    #[ORM\Column]
    private int $amount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        #[ORM\Column(length: 80)]
        private string $reference = 'INV-001',
        #[ORM\Column(nullable: true)]
        private ?int $organizationId = null
    ) {
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
