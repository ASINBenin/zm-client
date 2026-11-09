<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ReportSubmission;

class ReportSubmissionTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = ReportSubmission::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * Versions envoyées pour l'analyse, la plus récente d'abord.
     *
     * @return ReportSubmission[]
     */
    public function findNewestFirstByAnr(Anr $anr): array
    {
        return $this->getRepository()->findBy(['anr' => $anr], ['id' => 'DESC']);
    }

    /**
     * Vrai si un fichier de cette empreinte SHA-256 a déjà été envoyé pour l'analyse.
     */
    public function existsByAnrAndSha256(Anr $anr, string $sha256): bool
    {
        return $this->getRepository()->findOneBy(['anr' => $anr, 'sha256' => $sha256]) !== null;
    }

    /**
     * Demandes de signature du rapport final de l'analyse signées par tous leurs signataires, la plus récente d'abord.
     *
     * @return array{id: string, title: string, signedAt: string, path: string}[]
     */
    public function findSignedReports(Anr $anr): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, title, updated_at AS signedAt, file_signed AS path FROM signature_requests
             WHERE anr_id = ? AND status = ? AND deliverable_doc_type = ? AND file_signed IS NOT NULL
             ORDER BY id DESC',
            [$anr->getId(), 'signed', DeliveriesModels::MODEL_RISK_ANALYSIS]
        );
    }

    /**
     * Fichier PDF d'une demande de signature du rapport final de l'analyse, ou null si elle n'est pas signée.
     */
    public function findSignedReportPath(Anr $anr, int $signatureRequestId): ?string
    {
        $path = $this->entityManager->getConnection()->fetchOne(
            'SELECT file_signed FROM signature_requests
             WHERE id = ? AND anr_id = ? AND status = ? AND deliverable_doc_type = ? AND file_signed IS NOT NULL',
            [$signatureRequestId, $anr->getId(), 'signed', DeliveriesModels::MODEL_RISK_ANALYSIS]
        );

        return $path === false ? null : $path;
    }
}
