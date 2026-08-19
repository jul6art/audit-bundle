<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\DependencyInjection;

use Jul6Art\AuditBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsAudit(): void
    {
        self::assertSame('audit', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    public function testAuditingIsEnabledByDefault(): void
    {
        self::assertSame(self::defaults(), $this->process([]));
    }

    public function testItCanBeDisabled(): void
    {
        self::assertSame([...self::defaults(), 'enabled' => false], $this->process([['enabled' => false]]));
    }

    public function testLaterConfigsOverrideEarlierOnes(): void
    {
        self::assertSame(self::defaults(), $this->process([['enabled' => false], ['enabled' => true]]));
    }

    /**
     * `log_class` n'a **pas** de défaut : le bundle livre une superclasse mappée et ne peut pas
     * deviner quelle entité porte la piste. C'est l'extension qui refuse de se charger sans, avec
     * un message explicite — un défaut silencieux ici rendrait chaque `#[Auditable]` inopérant.
     */
    public function testTheLogClassHasNoDefault(): void
    {
        self::assertNull($this->process([])['log_class']);
    }

    public function testTheLogClassIsKept(): void
    {
        self::assertSame('App\\Entity\\AuditLog', $this->process([['log_class' => 'App\\Entity\\AuditLog']])['log_class']);
    }

    /**
     * Le défaut retire du diff le bruit qu'aucun lecteur ne veut : un simple horodatage bumpé
     * ne doit pas ressembler à un changement métier.
     */
    public function testTheTimestampFieldsAreIgnoredByDefault(): void
    {
        self::assertSame(['updatedAt', 'updatedBy'], $this->process([])['ignored_fields']);
    }

    public function testTheIgnoredFieldsCanBeReplaced(): void
    {
        self::assertSame(
            ['updatedAt', 'lastSeenAt'],
            $this->process([['ignored_fields' => ['updatedAt', 'lastSeenAt']]])['ignored_fields'],
        );
    }

    /**
     * @return array{enabled: bool, log_class: null, ignored_fields: list<string>}
     */
    private static function defaults(): array
    {
        return ['enabled' => true, 'log_class' => null, 'ignored_fields' => ['updatedAt', 'updatedBy']];
    }

    /**
     * enabled is a booleanNode, so it no longer silently accepts arbitrary scalars.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanValues(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['enabled' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [0];
        yield 'array' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
