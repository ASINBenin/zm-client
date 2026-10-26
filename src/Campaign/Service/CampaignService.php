<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTimeInterface;
use Monarc\FrontOffice\Campaign\Exception\BoApiException;
use Monarc\FrontOffice\Entity\Campaign;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Table\CampaignTable;

class CampaignService
{
    public const SYNCHRONIZATION_OK = 'ok';
    public const SYNCHRONIZATION_FAILED = 'failed';
    public const SYNCHRONIZATION_NOT_CONFIGURED = 'notConfigured';

    public function __construct(private CampaignSyncService $campaignSyncService, private CampaignTable $campaignTable)
    {
    }

    /**
     * Campagnes de l'entité, après une synchronisation avec le BO. Si elle échoue, ce sont les dernières reçues
     * et "synchronization" indique pourquoi.
     *
     * @return array{campaigns: array[], synchronization: string}
     */
    public function getCampaigns(): array
    {
        $synchronization = self::SYNCHRONIZATION_OK;
        try {
            $this->campaignSyncService->sync();
        } catch (InvalidConfigurationException) {
            $synchronization = self::SYNCHRONIZATION_NOT_CONFIGURED;
        } catch (BoApiException) {
            $synchronization = self::SYNCHRONIZATION_FAILED;
        }

        return [
            'campaigns' => array_map(
                static fn (Campaign $campaign) => [
                    'id' => $campaign->getId(),
                    'label' => $campaign->getLabel(),
                    'description' => $campaign->getDescription(),
                    'status' => $campaign->getStatus(),
                    'participationStatus' => $campaign->getParticipationStatus(),
                    'startDate' => $campaign->getStartDate()->format('Y-m-d'),
                    'responseDeadline' => $campaign->getResponseDeadline()->format('Y-m-d'),
                    'endDate' => $campaign->getEndDate()->format('Y-m-d'),
                    'isSignatureRequired' => $campaign->isSignatureRequired(),
                ],
                $this->campaignTable->findAllByStartDateDesc()
            ),
            'synchronization' => $synchronization,
        ];
    }
}
