<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * The concrete entity an application must declare — here reduced to the minimum the bundle
 * requires: an identifier, a table, and nothing else.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['action'])]
class AuditLog extends \Jul6Art\AuditBundle\Entity\AuditLog
{
    use IdTrait;
}
