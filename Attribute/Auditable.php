<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Attribute;

/**
 * Marks an entity for automatic audit logging through Doctrine's lifecycle events.
 *
 * ```php
 * #[ORM\Entity]
 * #[Auditable]
 * class Invoice { … }
 *
 * #[Auditable(onUpdate: false)]                              // creations and deletions only
 * #[Auditable(ignoredFields: ['updatedAt', 'lastSeenAt'])]   // replaces the global default
 * ```
 *
 * No column is added to the entity: the trail lives in its own table, and the actor is
 * resolved from the security token at the moment of the write.
 *
 * > ⚠️ **The attribute does nothing on its own.** `EventListener\AuditableListener` is what
 * > reads it, and it only writes rows once the application provides a concrete entity
 * > extending `Entity\AuditLog`. Annotate an entity in an application that never created that
 * > table and nothing happens — silently.
 *
 * @see \Jul6Art\AuditBundle\EventListener\AuditableListener
 * @see \Jul6Art\AuditBundle\Entity\AuditLog
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Auditable
{
    /**
     * @param bool              $onCreate      log an `<entity>.created` row on `postPersist`
     * @param bool              $onUpdate      log an `<entity>.updated` row on `postUpdate`, with a
     *                                         field diff — also the source of the `deleted` /
     *                                         `restored` rows a soft delete produces
     * @param bool              $onDelete      log an `<entity>.deleted` row on `preRemove`
     * @param list<string>|null $ignoredFields fields excluded from the update diff. Naming a
     *                                         list **replaces** the application-wide default
     *                                         (`audit.ignored_fields`, itself defaulting to
     *                                         `['updatedAt', 'updatedBy']`) rather than adding
     *                                         to it — so repeat the timestamp fields if you
     *                                         still want them out. Leave it `null` to inherit
     *                                         the default, which is what most entities want.
     */
    public function __construct(
        public bool $onCreate = true,
        public bool $onUpdate = true,
        public bool $onDelete = true,
        public ?array $ignoredFields = null,
    ) {
    }
}
