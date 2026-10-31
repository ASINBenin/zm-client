<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTimeInterface;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Campaign\Exception\BoApiException;
use Monarc\FrontOffice\Campaign\Provider\BoApiProvider;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ReportSubmission;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
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
        private DeliverableGenerationService $deliverableGenerationService,
        private CampaignTable $campaignTable,
        private ReportSubmissionTable $reportSubmissionTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUserEmail = $connectedUserService->getConnectedUser()->getEmail();
    }

    /**
     * Génère le rapport final en PDF et l'envoie au BO, qui en fait une nouvelle version.
     * Le rapport est généré à l'envoi : c'est l'état de l'analyse à cet instant qui est figé.
     *
     * @return array{submission: array}
     */
    public function submit(Anr $anr): array
    {
        $campaign = $this->campaignTable->findOneByAnr($anr);

        $report = $this->deliverableGenerationService->generateDeliverableWithValues(
            $anr,
            DeliveriesModels::MODEL_RISK_ANALYSIS,
            [
                'format' => 'pdf',
                'status' => 1,
                'docname' => $anr->getLabel(),
                'version' => (string)(\count($this->reportSubmissionTable->findNewestFirstByAnr($anr)) + 1),
            ]
        );

        try {
            $isSigned = $this->reportSubmissionTable->hasSignedSignatureRequest($anr);
            $boSubmission = $this->boApiProvider->submitReport(
                $report['path'],
                $anr->getId(),
                $anr->getLabel(),
                $isSigned,
                $this->connectedUserEmail,
                $campaign?->getBoCampaignId()
            );
        } catch (BoApiException $e) {
            throw $e->toUserFacingException();
        } finally {
            unlink($report['path']);
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

        return ['submission' => $this->getPreparedSubmissionData($submission)];
    }

    /**
     * Versions envoyées pour l'analyse, la plus récente d'abord.
     *
     * @return array{submissions: array[]}
     */
    public function getSubmissions(Anr $anr): array
    {
        return [
            'submissions' => array_map(
                fn (ReportSubmission $submission) => $this->getPreparedSubmissionData($submission),
                $this->reportSubmissionTable->findNewestFirstByAnr($anr)
            ),
        ];
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
        ];
    }
}
