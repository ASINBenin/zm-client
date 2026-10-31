<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Relance d'une campagne déjà envoyée.
 *
 * @ORM\Table(name="campaign_notifications", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_campaign_notification", columns={"bo_campaign_id", "type"})
 * })
 * @ORM\Entity
 */
class CampaignNotification
{
    public const TYPE_ANSWER_J7 = 'answer_j7';
    public const TYPE_ANSWER_J1 = 'answer_j1';
    public const TYPE_REPORT_J7 = 'report_j7';
    public const TYPE_REPORT_J1 = 'report_j1';

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(name="bo_campaign_id", type="integer")
     */
    protected int $boCampaignId;

    /**
     * @ORM\Column(name="type", type="string", length=30)
     */
    protected string $type;

    /**
     * @ORM\Column(name="sent_at", type="datetime")
     */
    protected DateTime $sentAt;

    public function __construct(int $boCampaignId, string $type)
    {
        $this->boCampaignId = $boCampaignId;
        $this->type = $type;
        $this->sentAt = new DateTime();
    }
}
