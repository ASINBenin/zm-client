<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Command;

use Monarc\FrontOffice\Campaign\Service\CampaignSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SyncCampaignsCommand extends Command
{
    protected static $defaultName = 'monarc:sync-campaigns';

    public function __construct(private CampaignSyncService $campaignSyncService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Fetches from the BackOffice the campaigns the entity is invited to.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $campaignsCount = $this->campaignSyncService->sync();
        } catch (Throwable $e) {
            $output->writeln('[' . date('Y-m-d H:i:s') . '] An error occurred: ' . $e->getMessage());

            return 1;
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Synchronized campaigns: ' . $campaignsCount);

        return 0;
    }
}
