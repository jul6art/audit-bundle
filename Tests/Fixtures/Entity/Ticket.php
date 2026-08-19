<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Names its own `ignoredFields`, which **replaces** the application-wide default — hence
 * `updatedAt` repeated here alongside the counter nobody wants in a diff.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ticket')]
#[Auditable(ignoredFields: ['updatedAt', 'viewCount'])]
class Ticket
{
    use IdTrait;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $subject = 'ticket'
    ) {
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function view(): static
    {
        ++$this->viewCount;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
