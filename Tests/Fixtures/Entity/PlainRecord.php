<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * No attribute: the listener must leave it entirely alone. Most entities of an application look
 * like this one, so "does nothing" is the behaviour that has to be cheap and certain.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plain_record')]
class PlainRecord
{
    use IdTrait;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $label = 'plain'
    ) {
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }
}
