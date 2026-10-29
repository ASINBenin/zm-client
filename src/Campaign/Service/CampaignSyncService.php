<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTimeImmutable;
use Monarc\FrontOffice\Campaign\Provider\BoApiProvider;
use Monarc\FrontOffice\Entity\Campaign;
use Monarc\FrontOffice\Table\CampaignTable;

class CampaignSyncService
{
    /**
     * Marge de relecture : les écritures sont idempotentes, alors qu'une modification validée dans le BO
     * juste avant l'heure qu'il renvoie pourrait sinon n'être jamais reçue.
     */
    private const SINCE_MARGIN_SECONDS = 60;

    public function __construct(private BoApiProvider $boApiProvider, private CampaignTable $campaignTable)
    {
    }

    /**
     * Récupère auprès du BO les campagnes créées ou modifiées depuis la dernière synchronisation.
     *
     * @return int Nombre de campagnes enregistrées.
     */
    public function sync(): int
    {
        $lastSyncedAt = $this->campaignTable->findLastSyncedAt();
        $since = $lastSyncedAt === null
            ? null
            : (new DateTimeImmutable())->setTimestamp($lastSyncedAt - self::SINCE_MARGIN_SECONDS);

        $response = $this->boApiProvider->getCampaigns($since);
        $syncedAt = $response['serverTime']->getTimestamp();
        foreach ($response['campaigns'] as $boCampaign) {
            $this->saveCampaign($boCampaign, $syncedAt);
        }
        $this->campaignTable->flush();

        return \count($response['campaigns']);
    }

    private function saveCampaign(array $boCampaign, int $syncedAt): void
    {
        $campaign = $this->campaignTable->findOneByBoCampaignId($boCampaign['id'])
            ?? (new Campaign($boCampaign['id']))->setCreator('BO');

        $campaign->setLabel($boCampaign['label'])
            ->setDescription($boCampaign['description'])
            ->setModelId($boCampaign['modelId'])
            ->setStatus($boCampaign['status'])
            ->setParticipationStatus($boCampaign['participation']['status'])
            ->setDates(
                $this->toDate($boCampaign['startDate']),
                $this->toDate($boCampaign['responseDeadline']),
                $this->toDate($boCampaign['endDate'])
            )
            ->setIsSignatureRequired($boCampaign['isSignatureRequired'])
            ->setSyncedAt($syncedAt)
            ->setUpdater('BO');

        $this->campaignTable->save($campaign, false);
    }

    private function toDate(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    }
}
