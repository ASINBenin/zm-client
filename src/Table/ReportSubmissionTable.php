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
     * Fichier PDF de la dernière demande de signature du rapport final de l'analyse signée par tous ses signataires,
     * ou null s'il n'y en a pas.
     */
    public function findLastSignedReportPath(Anr $anr): ?string
    {
        $path = $this->entityManager->getConnection()->fetchOne(
            'SELECT file_signed FROM signature_requests
             WHERE anr_id = ? AND status = ? AND deliverable_doc_type = ? AND file_signed IS NOT NULL
             ORDER BY id DESC LIMIT 1',
            [$anr->getId(), 'signed', DeliveriesModels::MODEL_RISK_ANALYSIS]
        );

        return $path === false ? null : $path;
    }
}
