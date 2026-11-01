<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Laminas\Diactoros\Response;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\ReportDraftService;
use Monarc\FrontOffice\Entity\Anr;

/**
 * Rapport final préparé de l'analyse : génération, puis lecture du PDF avant son envoi au BO.
 */
class ApiAnrPreparedReportsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ReportDraftService $reportDraftService)
    {
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse(['preparedReport' => $this->reportDraftService->prepare($anr)]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $path = $this->reportDraftService->getReportPath($anr, (string)$id);

        return new Response(fopen($path, 'rb'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string)filesize($path),
            'Content-Disposition' => 'inline; filename="rapport.pdf"',
        ]);
    }
}
