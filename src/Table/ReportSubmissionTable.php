<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
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
     * Une demande de signature de l'analyse est signée par tous ses signataires.
     */
    public function hasSignedSignatureRequest(Anr $anr): bool
    {
        return (int)$this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM signature_requests WHERE anr_id = ? AND status = ?',
            [$anr->getId(), 'signed']
        ) > 0;
    }
}
