<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\User\GetUsersInputFormatter;
use Monarc\Core\Service\IdentityManagementService;
use Monarc\Core\Service\PasswordService;
use Monarc\FrontOffice\Validator\InputValidator\User\PostUserDataInputValidator;
use Monarc\FrontOffice\Service\UserService;

class ApiAdminUsersController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    private GetUsersInputFormatter $getUsersInputFormatter;

    private PostUserDataInputValidator $postUserDataInputValidator;

    private UserService $userService;

    private PasswordService $passwordService;

    public function __construct(
        GetUsersInputFormatter $getUsersInputFormatter,
        PostUserDataInputValidator $postUserDataInputValidator,
        UserService $userService,
        PasswordService $passwordService,
        IdentityManagementService $identityManagementService
    ) {
        $this->getUsersInputFormatter = $getUsersInputFormatter;
        $this->postUserDataInputValidator = $postUserDataInputValidator;
        $this->userService = $userService;
        $this->passwordService = $passwordService;
        $this->identityManagementService = $identityManagementService;
    }

    public function getList()
    {
        $formattedInput = $this->getFormattedInputParams($this->getUsersInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->userService->getCount($formattedInput),
            'users' => $this->userService->getList($formattedInput),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->userService->getCompleteUser((int)$id));
    }

    public function create($data)
    {
        /** @var array $data */
        $this->validatePostParams($this->postUserDataInputValidator, $data);

        $this->userService->create($this->postUserDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function update($id, $data)
    {
        /** @var array $data */
        $this->validatePostParams($this->postUserDataInputValidator, $data);

        $this->userService->update((int)$id, $this->postUserDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        /** @var array $data */
        $this->userService->patch((int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function resetPasswordAction()
    {
        $id = (int)$this->params()->fromRoute('id');

        $this->passwordService->resetPassword($id);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        $this->userService->delete((int)$id);

        $this->getResponse()->setStatusCode(204);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * GET /api/identity-providers
     */
    public function identityProvidersAction()
    {
        return $this->getPreparedJsonResponse($this->identityManagementService->getActiveProviders());
    }
    /**
     * GET / POST / DELETE : /api/users/:id/identity
     */
    public function identityAction()
    {
        $userId = (int)$this->params()->fromRoute('id');
        $request = $this->getRequest();
        if ($request->isGet()) {
            return $this->getPreparedJsonResponse($this->identityManagementService->getUserIdentity($userId));
        }
        if ($request->isPost()) {
            $data = json_decode($request->getContent(), true) ?: [];
            if (empty($data['providerCode']) || empty($data['providerIdentifier'])) {
                $this->getResponse()->setStatusCode(400);
                return $this->getPreparedJsonResponse(['error' => 'Le fournisseur et l\'identifiant SSO sont requis.']);
            }
            try {
                $this->identityManagementService->linkUserIdentityById($userId, (string)$data['providerCode'], (string)$data['providerIdentifier']);
                return $this->getSuccessfulJsonResponse();
            } catch (\Throwable $e) {
                $this->getResponse()->setStatusCode(400);
                return $this->getPreparedJsonResponse(['error' => $e->getMessage()]);
            }
        }
        if ($request->isDelete()) {
            $this->identityManagementService->unlinkUserIdentityById($userId);
            return $this->getSuccessfulJsonResponse();
        }
        $this->getResponse()->setStatusCode(405);
        return $this->getPreparedJsonResponse(['error' => 'Method not allowed']);
    }
}
