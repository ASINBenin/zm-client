<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * Campagne du BO à laquelle l'entité est invitée : copie locale tenue à jour par CampaignSyncService.
 *
 * @ORM\Table(name="campaigns", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_campaign_bo_id", columns={"bo_campaign_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Campaign
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const STATUS_CLOSED = 'closed';

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * Identifiant de la campagne dans le BO.
     *
     * @ORM\Column(name="bo_campaign_id", type="integer")
     */
    protected int $boCampaignId;

    /**
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected string $label;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected ?string $description = null;

    /**
     * Modèle du BO sur lequel l'analyse de la campagne est créée.
     *
     * @ORM\Column(name="model_id", type="integer")
     */
    protected int $modelId;

    /**
     * @ORM\Column(name="status", type="string", length=20)
     */
    protected string $status;

    /**
     * @ORM\Column(name="participation_status", type="string", length=20)
     */
    protected string $participationStatus;

    /**
     * @ORM\Column(name="start_date", type="date_immutable")
     */
    protected DateTimeImmutable $startDate;

    /**
     * @ORM\Column(name="response_deadline", type="date_immutable")
     */
    protected DateTimeImmutable $responseDeadline;

    /**
     * @ORM\Column(name="end_date", type="date_immutable")
     */
    protected DateTimeImmutable $endDate;

    /**
     * @ORM\Column(name="is_signature_required", type="boolean")
     */
    protected bool $isSignatureRequired = false;

    /**
     * Analyse créée quand l'entité a participé ; null avant, ou si l'analyse a été supprimée.
     *
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    protected ?Anr $anr = null;

    /**
     * @ORM\Column(name="synced_at", type="bigint")
     */
    protected string $syncedAt;

    public function __construct(int $boCampaignId)
    {
        $this->boCampaignId = $boCampaignId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoCampaignId(): int
    {
        return $this->boCampaignId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function setModelId(int $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function getParticipationStatus(): string
    {
        return $this->participationStatus;
    }

    public function setParticipationStatus(string $participationStatus): self
    {
        $this->participationStatus = $participationStatus;

        return $this;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getResponseDeadline(): DateTimeImmutable
    {
        return $this->responseDeadline;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setDates(
        DateTimeImmutable $startDate,
        DateTimeImmutable $responseDeadline,
        DateTimeImmutable $endDate
    ): self {
        $this->startDate = $startDate;
        $this->responseDeadline = $responseDeadline;
        $this->endDate = $endDate;

        return $this;
    }

    public function isSignatureRequired(): bool
    {
        return $this->isSignatureRequired;
    }

    public function setIsSignatureRequired(bool $isSignatureRequired): self
    {
        $this->isSignatureRequired = $isSignatureRequired;

        return $this;
    }

    public function getAnr(): ?Anr
    {
        return $this->anr;
    }

    public function setAnr(Anr $anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    /**
     * Timestamp Unix de la synchronisation qui a écrit cette campagne.
     */
    public function getSyncedAt(): int
    {
        return (int)$this->syncedAt;
    }

    public function setSyncedAt(int $syncedAt): self
    {
        $this->syncedAt = (string)$syncedAt;

        return $this;
    }
}
