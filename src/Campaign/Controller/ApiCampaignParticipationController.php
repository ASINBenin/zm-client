<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\CampaignService;

/**
 * Participation de l'entité à une campagne, réservée à son administrateur.
 */
class ApiCampaignParticipationController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private CampaignService $campaignService)
    {
    }

    public function create($data)
    {
        return $this->getPreparedJsonResponse(
            $this->campaignService->participate((int)$this->params()->fromRoute('campaignId'))
        );
    }
}
