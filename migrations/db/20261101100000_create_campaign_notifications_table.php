<?php

use Phinx\Migration\AbstractMigration;

/**
 * Relances déjà envoyées : la tâche quotidienne ne les envoie qu'une fois par campagne.
 */
class CreateCampaignNotificationsTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('campaign_notifications', ['signed' => false])
            ->addColumn('bo_campaign_id', 'integer', $required + ['signed' => false])
            ->addColumn('type', 'string', $required + ['limit' => 30])
            ->addColumn('sent_at', 'datetime', $required)
            ->addIndex(['bo_campaign_id', 'type'], ['unique' => true, 'name' => 'uniq_campaign_notification'])
            ->create();
    }
}
