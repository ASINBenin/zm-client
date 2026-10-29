<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\CampaignAccessService;

/**
 * Utilisateurs autorisés sur l'analyse d'une campagne, réservés à l'administrateur de l'entité.
 */
class ApiCampaignAccessController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private CampaignAccessService $campaignAccessService)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse(
            $this->campaignAccessService->getAccess((int)$this->params()->fromRoute('campaignId'))
        );
    }

    public function replaceList($data)
    {
        return $this->getPreparedJsonResponse(
            $this->campaignAccessService->setAccess(
                (int)$this->params()->fromRoute('campaignId'),
                \is_array($data['accesses'] ?? null) ? $data['accesses'] : null
            )
        );
    }
}
