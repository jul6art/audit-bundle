<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Creations and deletions matter, edits do not — a draft is rewritten constantly and logging
 * every keystroke buries the trail.
 */
#[ORM\Entity]
#[ORM\Table(name: 'draft')]
#[Auditable(onUpdate: false)]
class Draft
{
    use IdTrait;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $body = 'draft'
    ) {
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }
}
