<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;

/**
 * Version du rapport d'une analyse envoyée au BO, dans une campagne ou hors campagne.
 *
 * @ORM\Table(name="report_submissions")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class ReportSubmission
{
    use CreateEntityTrait;

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Anr")
     * @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Anr $anr;

    /**
     * @ORM\Column(name="bo_submission_id", type="integer")
     */
    protected int $boSubmissionId;

    /**
     * @ORM\Column(name="bo_campaign_id", type="integer", nullable=true)
     */
    protected ?int $boCampaignId;

    /**
     * Numéro attribué par le BO.
     *
     * @ORM\Column(name="version", type="integer")
     */
    protected int $version;

    /**
     * @ORM\Column(name="file_size", type="integer")
     */
    protected int $fileSize;

    /**
     * @ORM\Column(name="sha256", type="string", length=64, options={"fixed": true})
     */
    protected string $sha256;

    /**
     * @ORM\Column(name="is_signed", type="boolean")
     */
    protected bool $isSigned;

    /**
     * Appréciation de l'ASIN, copie de celle du BO.
     *
     * @ORM\Column(name="appreciation_level", type="string", length=20, nullable=true)
     */
    protected ?string $appreciationLevel = null;

    /**
     * @ORM\Column(name="appreciation_comment", type="text", nullable=true)
     */
    protected ?string $appreciationComment = null;

    /**
     * @ORM\Column(name="appreciated_at", type="datetime", nullable=true)
     */
    protected ?DateTime $appreciatedAt = null;

    public function __construct(
        Anr $anr,
        int $boSubmissionId,
        ?int $boCampaignId,
        int $version,
        int $fileSize,
        string $sha256,
        bool $isSigned,
        string $creator
    ) {
        $this->anr = $anr;
        $this->boSubmissionId = $boSubmissionId;
        $this->boCampaignId = $boCampaignId;
        $this->version = $version;
        $this->fileSize = $fileSize;
        $this->sha256 = $sha256;
        $this->isSigned = $isSigned;
        $this->creator = $creator;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoSubmissionId(): int
    {
        return $this->boSubmissionId;
    }

    public function getBoCampaignId(): ?int
    {
        return $this->boCampaignId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function isSigned(): bool
    {
        return $this->isSigned;
    }

    public function getAppreciationLevel(): ?string
    {
        return $this->appreciationLevel;
    }

    public function getAppreciationComment(): ?string
    {
        return $this->appreciationComment;
    }

    public function getAppreciatedAt(): ?DateTime
    {
        return $this->appreciatedAt;
    }

    /**
     * Recopie l'appréciation du BO ; renvoie false si elle est déjà à jour.
     */
    public function copyAppreciation(string $level, ?string $comment, DateTime $appreciatedAt): bool
    {
        if ($this->appreciationLevel === $level
            && $this->appreciationComment === $comment
            && $this->appreciatedAt == $appreciatedAt
        ) {
            return false;
        }
        $this->appreciationLevel = $level;
        $this->appreciationComment = $comment;
        $this->appreciatedAt = $appreciatedAt;

        return true;
    }
}
