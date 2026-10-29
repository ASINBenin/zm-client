<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Campaign;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Table\CampaignTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;

/**
 * Utilisateurs de l'entité autorisés sur l'analyse d'une campagne. L'administrateur connecté garde tous les droits.
 */
class CampaignAccessService
{
    private User $connectedUser;

    public function __construct(
        private CampaignTable $campaignTable,
        private UserTable $userTable,
        private UserAnrTable $userAnrTable,
        ConnectedUserService $connectedUserService
    ) {
        /** @var User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    /**
     * Utilisateurs pouvant recevoir un accès, avec leur droit actuel : null (aucun), 0 (lecture) ou 1 (écriture).
     *
     * @return array{users: array[]}
     */
    public function getAccess(int $campaignId): array
    {
        $anr = $this->getAnr($campaignId);

        $users = [];
        foreach ($this->getAssignableUsers() as $user) {
            $users[] = [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'email' => $user->getEmail(),
                'rwd' => $this->userAnrTable->findByAnrAndUser($anr, $user)?->getRwd(),
            ];
        }

        return ['users' => $users];
    }

    /**
     * Remplace les accès : seuls les utilisateurs de $accesses (userId, rwd) gardent ou reçoivent un droit.
     *
     * @param array{userId: int, rwd: int}[]|null $accesses Liste vide : plus aucun accès ; null : requête invalide.
     */
    public function setAccess(int $campaignId, ?array $accesses): array
    {
        $anr = $this->getAnr($campaignId);
        if ($accesses === null) {
            throw new Exception('La liste des accès est obligatoire.', 412);
        }

        $rwdByUserId = [];
        foreach ($accesses as $access) {
            $rwd = $access['rwd'] ?? null;
            if (!isset($access['userId']) || !\in_array($rwd, [0, 1], true)) {
                throw new Exception(
                    'Chaque accès doit indiquer un utilisateur et un droit (0 : lecture, 1 : écriture).',
                    412
                );
            }
            $rwdByUserId[(int)$access['userId']] = $rwd;
        }

        $assignableUsers = [];
        foreach ($this->getAssignableUsers() as $user) {
            $assignableUsers[$user->getId()] = $user;
        }
        if (array_diff_key($rwdByUserId, $assignableUsers) !== []) {
            throw new Exception('Un utilisateur désigné n\'existe pas ou ne peut pas recevoir d\'accès.', 412);
        }

        foreach ($assignableUsers as $userId => $user) {
            $this->applyAccess($anr, $user, $rwdByUserId[$userId] ?? null);
        }
        $this->userAnrTable->flush();

        return $this->getAccess($campaignId);
    }

    private function applyAccess(Anr $anr, User $user, ?int $rwd): void
    {
        $userAnr = $this->userAnrTable->findByAnrAndUser($anr, $user);
        if ($rwd === null) {
            if ($userAnr !== null) {
                $user->removeUserAnr($userAnr);
                $this->userAnrTable->remove($userAnr, false);
            }

            return;
        }

        if ($userAnr === null) {
            $user->addUserAnr(
                (new UserAnr())->setAnr($anr)->setRwd($rwd)->setCreator($this->connectedUser->getEmail())
            );

            return;
        }
        $userAnr->setRwd($rwd)->setUpdater($this->connectedUser->getEmail());
    }

    /**
     * Les autres utilisateurs actifs : ni le compte système, ni l'administrateur connecté.
     *
     * @return User[]
     */
    private function getAssignableUsers(): array
    {
        return array_values(array_filter(
            $this->userTable->findAll(),
            fn (User $user) => $user->isActive()
                && !$user->isSystemUser()
                && $user->getId() !== $this->connectedUser->getId()
        ));
    }

    private function getAnr(int $campaignId): Anr
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->campaignTable->findById($campaignId, false);
        if ($campaign === null) {
            throw new Exception('Cette campagne n\'existe pas.', 412);
        }

        $anr = $campaign->getAnr();
        if ($anr === null) {
            throw new Exception('L\'entité doit d\'abord participer à la campagne.', 412);
        }

        return $anr;
    }
}
