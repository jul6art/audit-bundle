<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * An entity the trail does NOT audit, stamped by a `PreUpdate` callback — the shape of an
 * organisation whose storage counter moves in the same flush as an audited write. It counts its
 * `PreUpdate` calls: each one is an `UPDATE` sent to the database.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stamp')]
#[ORM\HasLifecycleCallbacks]
class Stamp
{
    use IdTrait;

    public static int $preUpdates = 0;

    #[ORM\Column]
    private int $counter = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $stampedAt = null;

    public function bump(): void
    {
        ++$this->counter;
    }

    #[ORM\PreUpdate]
    public function stamp(): void
    {
        ++self::$preUpdates;
        $this->stampedAt = new \DateTimeImmutable();
    }
}
