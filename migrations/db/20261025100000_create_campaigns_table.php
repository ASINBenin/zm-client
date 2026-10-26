<?php

use Phinx\Migration\AbstractMigration;

/**
 * Copie locale des campagnes du BO auxquelles l'entité est invitée (voir CampaignSyncService).
 */
class CreateCampaignsTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('campaigns', ['signed' => false])
            ->addColumn('bo_campaign_id', 'integer', $required + ['signed' => false])
            ->addColumn('label', 'string', $required + ['limit' => 255])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('status', 'string', $required + ['limit' => 20])
            ->addColumn('participation_status', 'string', $required + ['limit' => 20])
            ->addColumn('start_date', 'date', $required)
            ->addColumn('response_deadline', 'date', $required)
            ->addColumn('end_date', 'date', $required)
            ->addColumn('is_signature_required', 'boolean', $required + ['default' => false])
            // Instant (timestamp Unix) de la synchronisation qui a écrit la ligne : insensible aux fuseaux horaires
            ->addColumn('synced_at', 'biginteger', $required + ['signed' => false])
            ->addColumn('creator', 'string', $required + ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['bo_campaign_id'], ['unique' => true, 'name' => 'uniq_campaign_bo_id'])
            ->create();
    }
}
