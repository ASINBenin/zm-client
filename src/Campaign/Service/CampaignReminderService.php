<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTimeImmutable;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\MailService;
use Monarc\FrontOffice\Entity\Campaign;
use Monarc\FrontOffice\Entity\CampaignNotification;
use Monarc\FrontOffice\Entity\UserAnr;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Table\CampaignNotificationTable;
use Monarc\FrontOffice\Table\CampaignTable;
use Monarc\FrontOffice\Table\ReportSubmissionTable;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\UserTable;
use Throwable;

/**
 * Relances envoyées par la tâche quotidienne aux utilisateurs de l'entité : réponse attendue avant la date limite,
 * rapport attendu avant la fin de la campagne. Chaque relance part une seule fois par campagne.
 */
class CampaignReminderService
{
    private const LONG_NOTICE_DAYS = 7;

    public function __construct(
        private CampaignTable $campaignTable,
        private CampaignNotificationTable $campaignNotificationTable,
        private ReportSubmissionTable $reportSubmissionTable,
        private UserTable $userTable,
        private UserAnrTable $userAnrTable,
        private MailService $mailService,
        private ConfigService $configService
    ) {
    }

    /**
     * @return int Nombre de relances envoyées.
     */
    public function sendReminders(): int
    {
        $today = new DateTimeImmutable('today');
        $remindersCount = 0;
        foreach ($this->campaignTable->findAllByStartDateDesc() as $campaign) {
            if ($campaign->isClosed() || $today < $campaign->getStartDate()) {
                continue;
            }

            if ($campaign->getParticipationStatus() === 'invited') {
                $remindersCount += (int)$this->remind(
                    $campaign,
                    $this->getDaysLeft($today, $campaign->getResponseDeadline()),
                    CampaignNotification::TYPE_ANSWER_J7,
                    CampaignNotification::TYPE_ANSWER_J1,
                    $this->getAdministratorAddresses(),
                    sprintf('[MONARC] Campagne « %s » : démarrage attendu', $campaign->getLabel()),
                    'Votre entité n\'a pas encore démarré la campagne « %s », à laquelle la participation est '
                    . 'obligatoire. La date de démarrage attendue est le %s (%s). Pour participer, ouvrez le menu '
                    . '« Campagnes » de votre MONARC.',
                    $campaign->getResponseDeadline()
                );
            } elseif ($campaign->getAnr() !== null
                && $this->reportSubmissionTable->findNewestFirstByAnr($campaign->getAnr()) === []
            ) {
                $remindersCount += (int)$this->remind(
                    $campaign,
                    $this->getDaysLeft($today, $campaign->getEndDate()),
                    CampaignNotification::TYPE_REPORT_J7,
                    CampaignNotification::TYPE_REPORT_J1,
                    $this->getAnalysisAddresses($campaign),
                    sprintf('[MONARC] Campagne « %s » : rapport attendu', $campaign->getLabel()),
                    'Aucun rapport n\'a encore été envoyé pour la campagne « %s ». La campagne se termine le %s (%s). '
                    . 'Envoyez le rapport depuis la page de la campagne.',
                    $campaign->getEndDate()
                );
            }
        }

        return $remindersCount;
    }

    /**
     * La relance à 1 jour part dès que l'échéance est à 1 jour ou moins, celle à 7 jours entre 2 et 7 jours :
     * un jour sans tâche quotidienne ne fait pas perdre la relance. Elle n'est enregistrée que si un courriel est parti.
     *
     * @param string[] $addresses
     */
    private function remind(
        Campaign $campaign,
        int $daysLeft,
        string $longNoticeType,
        string $shortNoticeType,
        array $addresses,
        string $subject,
        string $messageTemplate,
        DateTimeImmutable $deadline
    ): bool {
        if ($daysLeft < 0 || $daysLeft > self::LONG_NOTICE_DAYS) {
            return false;
        }
        $type = $daysLeft <= 1 ? $shortNoticeType : $longNoticeType;
        if ($this->campaignNotificationTable->isSent($campaign->getBoCampaignId(), $type)) {
            return false;
        }

        $message = '<p>' . sprintf(
            htmlspecialchars($messageTemplate, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($campaign->getLabel(), ENT_QUOTES, 'UTF-8'),
            $deadline->format('d/m/Y'),
            $daysLeft === 0 ? 'aujourd\'hui' : sprintf('dans %d jour%s', $daysLeft, $daysLeft > 1 ? 's' : '')
        ) . '</p>' . sprintf(
            '<p><a href="%s">Ouvrir la campagne dans MONARC</a></p>',
            htmlspecialchars(
                $this->configService->getHost() . '/#/client/campaigns/' . $campaign->getId(),
                ENT_QUOTES,
                'UTF-8'
            )
        );
        $sentCount = 0;
        foreach (array_unique($addresses) as $address) {
            try {
                $this->mailService->send($address, $subject, $message, $this->configService->getEmail());
                $sentCount++;
            } catch (Throwable $e) {
                error_log(sprintf('Campaign reminder "%s" to %s failed: %s', $subject, $address, $e->getMessage()));
            }
        }
        if ($sentCount === 0) {
            return false;
        }
        $this->campaignNotificationTable->save(new CampaignNotification($campaign->getBoCampaignId(), $type));

        return true;
    }

    private function getDaysLeft(DateTimeImmutable $today, DateTimeImmutable $deadline): int
    {
        return (int)$today->diff($deadline)->format('%r%a');
    }

    /**
     * @return string[]
     */
    private function getAdministratorAddresses(): array
    {
        return $this->userTable->findActiveEmailsByRole(UserRole::SUPER_ADMIN_FO);
    }

    /**
     * Administrateurs de l'entité et utilisateurs ayant un accès à l'analyse de la campagne.
     *
     * @return string[]
     */
    private function getAnalysisAddresses(Campaign $campaign): array
    {
        $addresses = $this->getAdministratorAddresses();
        /** @var UserAnr $userAnr */
        foreach ($this->userAnrTable->findByAnr($campaign->getAnr()) as $userAnr) {
            if ($userAnr->getUser()->isActive()) {
                $addresses[] = $userAnr->getUser()->getEmail();
            }
        }

        return $addresses;
    }
}
