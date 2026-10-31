<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Campaign\Exception\BoApiException;
use Monarc\FrontOffice\Campaign\Provider\BoApiProvider;
use Monarc\FrontOffice\Entity\Campaign;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Service\AnrService;
use Monarc\FrontOffice\Table\CampaignTable;

class CampaignService
{
    public const SYNCHRONIZATION_OK = 'ok';
    public const SYNCHRONIZATION_FAILED = 'failed';
    public const SYNCHRONIZATION_NOT_CONFIGURED = 'notConfigured';

    public function __construct(
        private CampaignSyncService $campaignSyncService,
        private BoApiProvider $boApiProvider,
        private AnrService $anrService,
        private ConfigService $configService,
        private CampaignTable $campaignTable
    ) {
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
                fn (Campaign $campaign) => $this->getPreparedCampaignData($campaign),
                $this->campaignTable->findAllByStartDateDesc()
            ),
            'synchronization' => $synchronization,
        ];
    }

    /**
     * L'entité participe : le BO l'enregistre, puis l'analyse est créée à partir du modèle de la campagne.
     * Répétable : si l'analyse manque après un échec, elle est recréée sans que le BO soit modifié une seconde fois.
     */
    public function participate(int $campaignId): array
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->campaignTable->findById($campaignId, false);
        if ($campaign === null) {
            throw new Exception('Cette campagne n\'existe pas.', 412);
        }

        $campaign->setParticipationStatus($this->confirmParticipationWithBo($campaign));
        if ($campaign->getAnr() === null) {
            $campaign->setAnr($this->anrService->createBasedOnModel([
                'label' => $campaign->getLabel(),
                'description' => (string)$campaign->getDescription(),
                'model' => $campaign->getModelId(),
                'language' => $this->configService->getLanguage()['defaultLanguageIndex'],
            ]));
        }
        $this->campaignTable->save($campaign);

        return $this->getPreparedCampaignData($campaign);
    }

    private function confirmParticipationWithBo(Campaign $campaign): string
    {
        try {
            return $this->boApiProvider->participate($campaign->getBoCampaignId());
        } catch (BoApiException $e) {
            throw $e->toUserFacingException();
        }
    }

    private function getPreparedCampaignData(Campaign $campaign): array
    {
        return [
            'id' => $campaign->getId(),
            'label' => $campaign->getLabel(),
            'description' => $campaign->getDescription(),
            'status' => $campaign->getStatus(),
            'participationStatus' => $campaign->getParticipationStatus(),
            'anrId' => $campaign->getAnr()?->getId(),
            'startDate' => $campaign->getStartDate()->format('Y-m-d'),
            'responseDeadline' => $campaign->getResponseDeadline()->format('Y-m-d'),
            'endDate' => $campaign->getEndDate()->format('Y-m-d'),
            'isSignatureRequired' => $campaign->isSignatureRequired(),
        ];
    }
}
