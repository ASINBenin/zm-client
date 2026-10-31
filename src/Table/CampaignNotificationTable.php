<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\CampaignNotification;

class CampaignNotificationTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = CampaignNotification::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isSent(int $boCampaignId, string $type): bool
    {
        return $this->getRepository()->findOneBy(['boCampaignId' => $boCampaignId, 'type' => $type]) !== null;
    }
}
