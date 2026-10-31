<?php

use Phinx\Migration\AbstractMigration;

/**
 * Trace locale des rapports envoyés au BO : l'historique reste consultable si le BO est injoignable.
 */
class CreateReportSubmissionsTable extends AbstractMigration
{
    public function change(): void
    {
        $required = ['null' => false];

        $this->table('report_submissions', ['signed' => false])
            ->addColumn('anr_id', 'integer', $required + ['signed' => false])
            ->addColumn('bo_submission_id', 'integer', $required + ['signed' => false])
            // vide : rapport soumis hors campagne
            ->addColumn('bo_campaign_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('version', 'integer', $required + ['signed' => false])
            ->addColumn('file_size', 'integer', $required + ['signed' => false])
            ->addColumn('sha256', 'char', $required + ['limit' => 64])
            ->addColumn('is_signed', 'boolean', $required + ['default' => false])
            ->addColumn('creator', 'string', $required + ['limit' => 255])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addIndex(['anr_id'])
            ->addForeignKey('anr_id', 'anrs', 'id', ['delete' => 'CASCADE', 'update' => 'RESTRICT'])
            ->create();
    }
}
