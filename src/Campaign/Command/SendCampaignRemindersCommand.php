<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Command;

use Monarc\FrontOffice\Campaign\Service\CampaignReminderService;
use Monarc\FrontOffice\Campaign\Service\CampaignSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SendCampaignRemindersCommand extends Command
{
    protected static $defaultName = 'monarc:send-campaign-reminders';

    public function __construct(
        private CampaignSyncService $campaignSyncService,
        private CampaignReminderService $campaignReminderService
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Reminds the entity of the answer and report deadlines of its campaigns.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->campaignSyncService->sync();
        } catch (Throwable $e) {
            // Sans le BO, les relances partent avec les dernières campagnes reçues
            $output->writeln('[' . date('Y-m-d H:i:s') . '] Synchronization skipped: ' . $e->getMessage());
        }

        try {
            $remindersCount = $this->campaignReminderService->sendReminders();
        } catch (Throwable $e) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] An error occurred: ' . $e->getMessage());

            return 1;
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Reminders sent: ' . $remindersCount);

        return 0;
    }
}
