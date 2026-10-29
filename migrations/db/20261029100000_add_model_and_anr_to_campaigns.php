<?php

use Phinx\Migration\AbstractMigration;

/**
 * Modèle de la campagne (envoyé par le BO) et analyse créée quand l'entité participe.
 */
class AddModelAndAnrToCampaigns extends AbstractMigration
{
    public function up(): void
    {
        // Simple copie de l'état du BO, rechargée en entier à la prochaine synchronisation : elle doit recevoir model_id
        $this->execute('DELETE FROM campaigns');

        $this->table('campaigns')
            ->addColumn('model_id', 'integer', ['null' => false, 'signed' => false, 'after' => 'description'])
            ->addColumn('anr_id', 'integer', ['null' => true, 'signed' => false, 'after' => 'is_signature_required'])
            ->addForeignKey('anr_id', 'anrs', 'id', ['delete' => 'SET_NULL', 'update' => 'RESTRICT'])
            ->update();
    }

    public function down(): void
    {
        // Deux étapes : la clé étrangère doit être retirée avant les colonnes
        $this->table('campaigns')->dropForeignKey('anr_id')->update();
        $this->table('campaigns')->removeColumn('anr_id')->removeColumn('model_id')->update();
    }
}
