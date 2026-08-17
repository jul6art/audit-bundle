<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Attribute;

use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\AuditBundle\Tests\Fixtures\AuditedEntity;
use Jul6Art\AuditBundle\Tests\Fixtures\FullyAuditedEntity;
use Jul6Art\AuditBundle\Tests\Fixtures\PlainEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Auditable::class)]
final class AuditableTest extends TestCase
{
    /**
     * It used to be a Doctrine annotation implementing Doctrine\ORM\Mapping\Annotation,
     * an interface removed in ORM 3. It is now a native PHP attribute.
     */
    public function testItIsANativeAttribute(): void
    {
        $attributes = new \ReflectionClass(Auditable::class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        self::assertSame(\Attribute::TARGET_CLASS, $attributes[0]->newInstance()->flags);
    }

    public function testItDefaultsToAuditingEveryEvent(): void
    {
        self::assertSame([], new Auditable()->getEvents());
    }

    public function testItKeepsTheConfiguredEvents(): void
    {
        self::assertSame(['created', 'deleted'], new Auditable(['created', 'deleted'])->getEvents());
    }

    public function testItAcceptsTheEventsAsANamedArgument(): void
    {
        self::assertSame(['viewed'], new Auditable(events: ['viewed'])->getEvents());
    }

    public function testItIsReadableFromAnAnnotatedClass(): void
    {
        $attributes = new \ReflectionClass(AuditedEntity::class)->getAttributes(Auditable::class);

        self::assertCount(1, $attributes);
        self::assertSame(['created', 'edited'], $attributes[0]->newInstance()->getEvents());
    }

    public function testAnAttributeWithoutArgumentsAuditsEverything(): void
    {
        $attributes = new \ReflectionClass(FullyAuditedEntity::class)->getAttributes(Auditable::class);

        self::assertCount(1, $attributes);
        self::assertSame([], $attributes[0]->newInstance()->getEvents());
    }

    public function testAClassWithoutTheAttributeExposesNone(): void
    {
        self::assertSame([], new \ReflectionClass(PlainEntity::class)->getAttributes(Auditable::class));
    }
}
