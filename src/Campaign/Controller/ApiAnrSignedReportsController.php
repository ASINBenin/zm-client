<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Laminas\Diactoros\Response;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\ReportSubmissionService;
use Monarc\FrontOffice\Entity\Anr;

/**
 * Rapports finaux de l'analyse signés dans le module de signature : liste et lecture du PDF signé.
 */
class ApiAnrSignedReportsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ReportSubmissionService $reportSubmissionService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->reportSubmissionService->getSignedReports($anr));
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $path = $this->reportSubmissionService->getSignedReportPath($anr, (int)$id);

        return new Response(fopen($path, 'rb'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string)filesize($path),
            'Content-Disposition' => 'inline; filename="rapport-signe.pdf"',
        ]);
    }
}
