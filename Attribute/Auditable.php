<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Attribute;

/**
 * Marks a class as auditable.
 *
 * This used to be a Doctrine annotation implementing
 * Doctrine\ORM\Mapping\Annotation, an interface removed in Doctrine ORM 3.
 *
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/attributes-reference.html
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Auditable
{
    /**
     * @param list<string> $events events to audit; an empty list means all of them
     */
    public function __construct(
        private array $events = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
