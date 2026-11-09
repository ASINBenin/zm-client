<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Campaign\Service\CampaignService;

/**
 * Campagnes auxquelles l'entité est invitée, réservées à son administrateur.
 */
class ApiCampaignsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private CampaignService $campaignService)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse($this->campaignService->getCampaigns());
    }

    /**
     * @param string $id
     */
    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->campaignService->getCampaign((int)$id));
    }
}
