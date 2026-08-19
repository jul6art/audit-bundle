<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row per audited action: what happened, to what, by whom, from where.
 *
 * This is a **mapped superclass**, not an entity. The application declares the concrete class,
 * which is what lets it own the table name, the indexes it needs, and whatever it wants to
 * expose — an API resource, a retention policy, a role check — none of which belongs in a
 * bundle:
 *
 * ```php
 * #[ORM\Entity(repositoryClass: AuditLogRepository::class)]
 * #[ORM\Table(name: 'audit_log')]
 * #[ORM\Index(columns: ['action'])]
 * #[ORM\Index(columns: ['created_at'])]
 * #[Purgeable(field: 'createdAt', interval: '-3 months')]
 * class AuditLog extends \Jul6Art\AuditBundle\Entity\AuditLog
 * {
 *     use IdTrait;
 * }
 * ```
 *
 * > ⚠️ **The identifier is deliberately absent here.** A mapped superclass may not declare the
 * > primary key of its children, so the concrete class brings its own — `IdTrait` from
 * > jul6art/core-bundle, or a hand-written `$id`. Forgetting it fails at mapping time with a
 * > clear message, which is the good kind of failure.
 *
 * Two things this class does not do, on purpose:
 *
 * - **No index.** Indexes belong to the concrete table, and which ones you need depends on how
 *   you read the trail. The example above is the minimum for a chronological screen filtered
 *   by action; add `organization_id`, `user_id` or `(target_type, target_id)` when a screen
 *   actually queries them.
 * - **No relation to your user class.** `userId` and `impersonatorId` are plain integers, not
 *   associations. An audit row must survive the deletion of the account it mentions — a
 *   foreign key would either block the deletion or erase the trail.
 */
#[ORM\MappedSuperclass]
abstract class AuditLog
{
    #[ORM\Column(nullable: true)]
    protected ?int $organizationId = null;

    #[ORM\Column(nullable: true)]
    protected ?int $userId = null;

    /**
     * Account that started the impersonation under which the action happened, `null` on a
     * genuine login. Filled from the current `SwitchUserToken` — see
     * {@see \Jul6Art\AuditBundle\Service\AuditLogger}. Without it the trail says the
     * impersonated user did what an administrator did in their name.
     */
    #[ORM\Column(nullable: true)]
    protected ?int $impersonatorId = null;

    #[ORM\Column(length: 120)]
    protected string $action;

    #[ORM\Column(length: 80, nullable: true)]
    protected ?string $targetType = null;

    /**
     * Stored as a string so a UUID and an auto-increment id fit the same column.
     */
    #[ORM\Column(length: 80, nullable: true)]
    protected ?string $targetId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $payload = null;

    /** @var array<string, array{old: mixed, new: mixed}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $diff = null;

    #[ORM\Column(length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        string $action,
        ?int $organizationId = null,
        ?int $userId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $payload = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $impersonatorId = null,
    ) {
        $this->action = $action;
        $this->organizationId = $organizationId;
        $this->userId = $userId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->payload = $payload;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->impersonatorId = $impersonatorId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getImpersonatorId(): ?int
    {
        return $this->impersonatorId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    /** @return array<string, mixed>|null */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<string, array{old: mixed, new: mixed}>|null */
    public function getDiff(): ?array
    {
        return $this->diff;
    }

    /** @param array<string, array{old: mixed, new: mixed}>|null $diff */
    public function setDiff(?array $diff): static
    {
        $this->diff = $diff;

        return $this;
    }
}
