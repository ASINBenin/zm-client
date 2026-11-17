<?php

use Phinx\Migration\AbstractMigration;

/**
 * Statistiques inter-entités : la collecte devient obligatoire pour toutes les analyses (hors snapshots).
 */
class ForceStatsCollection extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'UPDATE `anrs` SET `is_stats_collected` = 1
            WHERE `id` NOT IN (SELECT `anr_id` FROM `snapshots`)'
        );
    }

    public function down(): void
    {
    }
}
