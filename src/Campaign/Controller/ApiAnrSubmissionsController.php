<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\ReportSubmissionService;
use Monarc\FrontOffice\Entity\Anr;

/**
 * Rapports de l'analyse envoyés au BO : nouvel envoi et historique des versions.
 */
class ApiAnrSubmissionsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ReportSubmissionService $reportSubmissionService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->reportSubmissionService->getSubmissions($anr));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->reportSubmissionService->submit($anr, $data));
    }
}
