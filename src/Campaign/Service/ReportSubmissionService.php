<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Campaign\Exception\BoApiException;
use Monarc\FrontOffice\Campaign\Provider\BoApiProvider;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ReportSubmission;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Table\CampaignTable;
use Monarc\FrontOffice\Table\ReportSubmissionTable;

/**
 * Envoi au BO du rapport d'une analyse, dans la campagne qui la porte ou hors campagne pour une analyse libre.
 */
class ReportSubmissionService
{
    private string $connectedUserEmail;

    public function __construct(
        private BoApiProvider $boApiProvider,
        private ReportDraftService $reportDraftService,
        private CampaignTable $campaignTable,
        private ReportSubmissionTable $reportSubmissionTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUserEmail = $connectedUserService->getConnectedUser()->getEmail();
    }

    /**
     * Envoie au BO, qui en fait une nouvelle version, un rapport que l'utilisateur a déjà eu sous les yeux :
     * soit le rapport préparé (data.token), soit un rapport signé du module de signature (data.signatureRequestId).
     * Une campagne qui exige la signature n'accepte que le rapport signé.
     *
     * @param array{token?: string, signatureRequestId?: int} $data
     *
     * @return array{submission: array}
     */
    public function submit(Anr $anr, array $data): array
    {
        $campaign = $this->campaignTable->findOneByAnr($anr);
        $isSigned = !empty($data['signatureRequestId']);
        if ($isSigned) {
            $reportPath = $this->getSignedReportPath($anr, (int)$data['signatureRequestId']);
        } elseif (!empty($data['token'])) {
            if ($campaign?->isSignatureRequired()) {
                throw new Exception(
                    'Cette campagne exige un rapport signé : faites signer le rapport final avant de l\'envoyer.',
                    412
                );
            }
            $reportPath = $this->reportDraftService->getReportPath($anr, (string)$data['token']);
        } else {
            throw new Exception('Préparez le rapport, relisez-le, puis soumettez-le.', 412);
        }

        try {
            $boSubmission = $this->boApiProvider->submitReport(
                $reportPath,
                $anr->getId(),
                $anr->getLabel(),
                $isSigned,
                $this->connectedUserEmail,
                $campaign?->getBoCampaignId()
            );
        } catch (BoApiException $e) {
            throw $e->toUserFacingException();
        }

        $submission = new ReportSubmission(
            $anr,
            (int)$boSubmission['id'],
            $campaign?->getBoCampaignId(),
            (int)$boSubmission['version'],
            (int)$boSubmission['fileSize'],
            (string)$boSubmission['sha256'],
            $isSigned,
            $this->connectedUserEmail
        );
        $this->reportSubmissionTable->save($submission);
        if (!$isSigned) {
            // Le rapport préparé ne sert qu'une fois ; le PDF signé reste dans le module de signature
            unlink($reportPath);
        }

        return ['submission' => $this->getPreparedSubmissionData($submission)];
    }

    /**
     * Rapports finaux de l'analyse signés par tous leurs signataires, le plus récent d'abord.
     *
     * @return array{signedReports: array[]}
     */
    public function getSignedReports(Anr $anr): array
    {
        $signedReports = [];
        foreach ($this->reportSubmissionTable->findSignedReports($anr) as $row) {
            if (is_file($row['path'])) {
                $signedReports[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'signedAt' => (new DateTime($row['signedAt']))->format(DateTimeInterface::ATOM),
                    'fileSize' => filesize($row['path']),
                ];
            }
        }

        return ['signedReports' => $signedReports];
    }

    /**
     * Fichier PDF d'un rapport signé de l'analyse ; 412 s'il n'existe pas pour cette analyse.
     */
    public function getSignedReportPath(Anr $anr, int $signatureRequestId): string
    {
        $path = $this->reportSubmissionTable->findSignedReportPath($anr, $signatureRequestId);
        if ($path === null || !is_file($path)) {
            throw new Exception('Ce rapport signé n\'existe pas pour cette analyse.', 412);
        }

        return $path;
    }

    /**
     * Versions envoyées pour l'analyse, la plus récente d'abord, avec l'appréciation de l'ASIN. Elle est relue du BO ;
     * s'il est injoignable, ce sont les dernières appréciations reçues et "synchronization" indique pourquoi.
     *
     * @return array{submissions: array[], synchronization: string}
     */
    public function getSubmissions(Anr $anr): array
    {
        $submissions = $this->reportSubmissionTable->findNewestFirstByAnr($anr);
        $synchronization = $submissions === []
            ? CampaignService::SYNCHRONIZATION_OK
            : $this->refreshAppreciations($submissions);

        return [
            'submissions' => array_map(
                fn (ReportSubmission $submission) => $this->getPreparedSubmissionData($submission),
                $submissions
            ),
            'synchronization' => $synchronization,
        ];
    }

    /**
     * @param ReportSubmission[] $submissions
     *
     * @return string Résultat de la synchronisation (constantes de CampaignService).
     */
    private function refreshAppreciations(array $submissions): string
    {
        try {
            $boSubmissions = $this->boApiProvider->getSubmissions();
        } catch (InvalidConfigurationException) {
            return CampaignService::SYNCHRONIZATION_NOT_CONFIGURED;
        } catch (BoApiException) {
            return CampaignService::SYNCHRONIZATION_FAILED;
        }

        $appreciations = [];
        foreach ($boSubmissions as $boSubmission) {
            if (isset($boSubmission['id'], $boSubmission['appreciation']['level'])) {
                $appreciations[(int)$boSubmission['id']] = $boSubmission['appreciation'];
            }
        }
        foreach ($submissions as $submission) {
            $appreciation = $appreciations[$submission->getBoSubmissionId()] ?? null;
            if ($appreciation !== null && $submission->copyAppreciation(
                $appreciation['level'],
                $appreciation['comment'] ?? null,
                (new DateTime($appreciation['appreciatedAt']))->setTimezone(new DateTimeZone(date_default_timezone_get()))
            )) {
                $this->reportSubmissionTable->save($submission);
            }
        }

        return CampaignService::SYNCHRONIZATION_OK;
    }

    private function getPreparedSubmissionData(ReportSubmission $submission): array
    {
        return [
            'id' => $submission->getId(),
            'boCampaignId' => $submission->getBoCampaignId(),
            'version' => $submission->getVersion(),
            'fileSize' => $submission->getFileSize(),
            'sha256' => $submission->getSha256(),
            'isSigned' => $submission->isSigned(),
            'submittedBy' => $submission->getCreator(),
            'submittedAt' => $submission->getCreatedAt()->format(DateTimeInterface::ATOM),
            'appreciation' => $submission->getAppreciationLevel() === null ? null : [
                'level' => $submission->getAppreciationLevel(),
                'comment' => $submission->getAppreciationComment(),
                'appreciatedAt' => $submission->getAppreciatedAt()->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
