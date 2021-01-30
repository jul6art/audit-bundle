<?php

namespace Jul6Art\AuditBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\ORM\Mapping\Annotation as AnnotationInterface;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * Class Auditable
 */
class Auditable implements AnnotationInterface
{
    /**
     * @var string[]
     */
    private $events = [];

    /**
     * Asyncable constructor.
     * @param string $event
     */
    public function __construct(array $params)
    {
        if (array_key_exists('events', $params) and \is_iterable($params['events'])) {
            $this->events = $params['events'];
        }
    }

    /**
     * @return string[]
     */
    public function getEvents(): iterable
    {
        return $this->events;
    }
}

