<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Attribute;

use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\AuditBundle\Tests\Fixtures\AuditedEntity;
use Jul6Art\AuditBundle\Tests\Fixtures\FullyAuditedEntity;
use Jul6Art\AuditBundle\Tests\Fixtures\PlainEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * L'API de l'attribut a changé en v3.0.0 : la liste d'événements (`getEvents()`) a laissé la
 * place aux trois interrupteurs et à `ignoredFields`, qui est ce dont un projet a réellement
 * besoin — décider quel événement compte, et quel champ est du bruit.
 */
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

    public function testItAuditsTheThreeEventsByDefault(): void
    {
        $auditable = new Auditable();

        self::assertTrue($auditable->onCreate);
        self::assertTrue($auditable->onUpdate);
        self::assertTrue($auditable->onDelete);
    }

    /**
     * `null` et non un tableau : c'est ce qui distingue « je n'ai rien demandé » de « je veux
     * exactement cette liste », et donc ce qui permet au défaut applicatif
     * (`audit.ignored_fields`) de s'appliquer.
     */
    public function testItNamesNoIgnoredFieldByDefault(): void
    {
        self::assertNull(new Auditable()->ignoredFields);
    }

    public function testAnEventCanBeSwitchedOffOnItsOwn(): void
    {
        $auditable = new Auditable(onUpdate: false);

        self::assertTrue($auditable->onCreate);
        self::assertFalse($auditable->onUpdate);
        self::assertTrue($auditable->onDelete);
    }

    public function testItKeepsTheIgnoredFieldsItWasGiven(): void
    {
        self::assertSame(['updatedAt', 'viewCount'], new Auditable(ignoredFields: ['updatedAt', 'viewCount'])->ignoredFields);
    }

    public function testItIsReadableFromAnAnnotatedClass(): void
    {
        $attributes = new \ReflectionClass(AuditedEntity::class)->getAttributes(Auditable::class);

        self::assertCount(1, $attributes);

        $auditable = $attributes[0]->newInstance();
        self::assertFalse($auditable->onUpdate);
        self::assertSame(['lastSeenAt'], $auditable->ignoredFields);
    }

    public function testAnAttributeWithoutArgumentsAuditsEverything(): void
    {
        $attributes = new \ReflectionClass(FullyAuditedEntity::class)->getAttributes(Auditable::class);

        self::assertCount(1, $attributes);

        $auditable = $attributes[0]->newInstance();
        self::assertTrue($auditable->onCreate);
        self::assertTrue($auditable->onUpdate);
        self::assertTrue($auditable->onDelete);
        self::assertNull($auditable->ignoredFields);
    }

    public function testAClassWithoutTheAttributeExposesNone(): void
    {
        self::assertSame([], new \ReflectionClass(PlainEntity::class)->getAttributes(Auditable::class));
    }
}
