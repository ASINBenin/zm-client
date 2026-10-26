<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Campaign;

class CampaignTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Campaign::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByBoCampaignId(int $boCampaignId): ?Campaign
    {
        return $this->getRepository()->findOneBy(['boCampaignId' => $boCampaignId]);
    }

    /**
     * @return Campaign[]
     */
    public function findAllByStartDateDesc(): array
    {
        return $this->getRepository()->findBy([], ['startDate' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Instant (timestamp Unix) de la dernière synchronisation, ou null si aucune campagne n'a encore été reçue.
     */
    public function findLastSyncedAt(): ?int
    {
        $syncedAt = $this->getRepository()->createQueryBuilder('c')
            ->select('MAX(c.syncedAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return $syncedAt === null ? null : (int)$syncedAt;
    }
}
