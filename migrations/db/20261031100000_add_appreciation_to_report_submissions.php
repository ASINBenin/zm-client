<?php

use Phinx\Migration\AbstractMigration;

/**
 * Appréciation de l'ASIN sur une version du rapport, recopiée du BO à l'ouverture de l'historique.
 */
class AddAppreciationToReportSubmissions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('report_submissions')
            ->addColumn('appreciation_level', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('appreciation_comment', 'text', ['null' => true])
            ->addColumn('appreciated_at', 'datetime', ['null' => true])
            ->update();
    }
}
