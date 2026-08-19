<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\EventListener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Jul6Art\AuditBundle\Attribute\Auditable;
use Jul6Art\AuditBundle\Service\ActorResolver;
use Jul6Art\AuditBundle\Service\AuditLogger;

/**
 * What makes `#[Auditable]` do something: a Doctrine listener on `postPersist`, `postUpdate`
 * and `preRemove` that turns each write into a row of the trail.
 *
 * Nothing to wire — the bundle registers it on the three events as soon as
 * `audit.enabled` is true. Annotate an entity and it is audited.
 *
 * ## What a row says
 *
 * The action is `<entity>.<event>` in lower case (`invoice.created`, `user.updated`), the
 * target is the entity's short class name and its id, and the organisation is read from
 * `getOrganization()->getId()` or `getOrganizationId()` when either exists — so a multi-tenant
 * application gets its scope for free and a single-tenant one records `null`.
 *
 * An update carries a diff of the changed fields under `payload.diff`, each field as
 * `{old, new}`. Values are flattened so the row stays readable and serialisable: a date becomes
 * ISO 8601, an enum its backing value, an entity its id.
 *
 * ## Soft delete reads as a deletion, not an update
 *
 * A soft delete is technically an `UPDATE` of `deletedAt`, and logging it as `updated` makes a
 * trail nobody can read. When the change set shows `deletedAt` going from `null` to a date the
 * row is recorded as `deleted`, and as `restored` on the way back. This is why an application
 * using soft delete does **not** need to log those actions by hand — doing both produces
 * duplicates.
 *
 * ## Silencing it during a fan-out
 *
 * ```php
 * $this->auditableListener->startSkip();
 * try {
 *     $this->provisionEverything();   // hundreds of audited entities
 * } finally {
 *     $this->auditableListener->endSkip();
 * }
 * ```
 *
 * Coarse on purpose: a flow that provisions a plan already writes one meaningful row
 * (`feature.assigned`), and the per-entity trail underneath is noise that costs a listener
 * cascade per row. Use it when the caller records the intent itself — never to hide writes.
 *
 * > ⚠️ **`startSkip()` must be closed in a `finally`.** It nests, and a leaked depth silently
 * > disables auditing for the rest of the request.
 *
 * @see Auditable
 * @see AuditLogger::startBatch() for the complementary tool — batching the writes rather than
 *      skipping them
 */
// Not final: applications mock this class to assert that a service opens and closes a skip
// window, and PHPUnit cannot mock a final class.
class AuditableListener
{
    /**
     * Above zero, every automatic audit is skipped. Stackable — see {@see startSkip()}.
     */
    private int $skipDepth = 0;

    /**
     * @param list<string> $ignoredFields default applied to entities whose attribute does not
     *                                    name its own list
     */
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly array $ignoredFields = ['updatedAt', 'updatedBy'],
        private readonly ?ActorResolver $actorResolver = null,
    ) {
    }

    public function startSkip(): void
    {
        ++$this->skipDepth;
    }

    public function endSkip(): void
    {
        if ($this->skipDepth > 0) {
            --$this->skipDepth;
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if ($this->skipDepth > 0) {
            return;
        }

        $entity = $args->getObject();
        $attribute = self::auditableAttribute($entity);

        if (null === $attribute || !$attribute->onCreate) {
            return;
        }

        $this->log($entity, 'created');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        if ($this->skipDepth > 0) {
            return;
        }

        $entity = $args->getObject();
        $attribute = self::auditableAttribute($entity);

        if (null === $attribute || !$attribute->onUpdate) {
            return;
        }

        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
        $diff = $this->buildDiff($changeSet, $attribute->ignoredFields ?? $this->ignoredFields);

        // Une suppression douce est un UPDATE de deletedAt : la consigner comme « updated »
        // rendrait la piste illisible. On lit la transition et on nomme l'action.
        if (isset($changeSet['deletedAt'])) {
            $old = $changeSet['deletedAt'][0] ?? null;
            $new = $changeSet['deletedAt'][1] ?? null;

            if (null === $old && null !== $new) {
                $this->log($entity, 'deleted', [] !== $diff ? ['diff' => $diff] : []);

                return;
            }

            if (null !== $old && null === $new) {
                $this->log($entity, 'restored', [] !== $diff ? ['diff' => $diff] : []);

                return;
            }
        }

        // Un changement entièrement ignoré n'est pas un changement : pas de ligne.
        if ([] === $diff) {
            return;
        }

        $this->log($entity, 'updated', ['diff' => $diff]);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if ($this->skipDepth > 0) {
            return;
        }

        $entity = $args->getObject();
        $attribute = self::auditableAttribute($entity);

        if (null === $attribute || !$attribute->onDelete) {
            return;
        }

        $this->log($entity, 'deleted');
    }

    private static function auditableAttribute(object $entity): ?Auditable
    {
        $attributes = new \ReflectionClass($entity)->getAttributes(Auditable::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(object $entity, string $event, array $payload = []): void
    {
        $entityType = self::shortName($entity);

        $this->auditLogger->log(
            strtolower($entityType).'.'.$event,
            self::resolveOrganizationId($entity),
            $this->actorResolver?->getCurrentUserIdOrNull(),
            $entityType,
            method_exists($entity, 'getId') ? $entity->getId() : null,
            [] !== $payload ? $payload : null,
        );
    }

    private static function shortName(object $entity): string
    {
        $parts = explode('\\', $entity::class);

        return end($parts);
    }

    /**
     * Both shapes are supported because both exist in practice: an association to an
     * organisation entity, or a denormalised column.
     */
    private static function resolveOrganizationId(object $entity): ?int
    {
        if (method_exists($entity, 'getOrganization') && null !== $entity->getOrganization()) {
            $organization = $entity->getOrganization();

            if (method_exists($organization, 'getId') && is_numeric($id = $organization->getId())) {
                return (int) $id;
            }

            return null;
        }

        if (method_exists($entity, 'getOrganizationId') && is_numeric($id = $entity->getOrganizationId())) {
            return (int) $id;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $changeSet
     * @param list<string>         $ignoredFields
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildDiff(array $changeSet, array $ignoredFields): array
    {
        $diff = [];

        foreach ($changeSet as $field => $change) {
            if (!\is_array($change) || \count($change) < 2) {
                continue;
            }

            if (\in_array($field, $ignoredFields, true)) {
                continue;
            }

            [$old, $new] = $change;

            $diff[$field] = [
                'old' => self::normalizeValue($old),
                'new' => self::normalizeValue($new),
            ];
        }

        return $diff;
    }

    /**
     * Flattens a value into something a JSON column can hold and a human can read.
     */
    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (\is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }

        return $value;
    }
}
