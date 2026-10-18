<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Signature : suppression du statut "draft" et unicité des signataires (un utilisateur et un ordre par demande).
 */
class HardenSignatureTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE signature_requests SET status = 'pending_configuration' WHERE status = 'draft'");
        $this->table('signature_requests')
            ->changeColumn('status', 'string', ['limit' => 50, 'default' => 'pending_configuration'])
            ->update();

        $this->table('signature_signatories')
            ->addIndex(['request_id', 'user_id'], ['unique' => true, 'name' => 'uniq_signatory_user'])
            ->addIndex(['request_id', 'order_index'], ['unique' => true, 'name' => 'uniq_signatory_order'])
            ->update();
    }

    public function down(): void
    {
        $this->table('signature_signatories')
            ->removeIndexByName('uniq_signatory_order')
            ->removeIndexByName('uniq_signatory_user')
            ->update();

        $this->table('signature_requests')
            ->changeColumn('status', 'string', ['limit' => 50, 'default' => 'draft'])
            ->update();
    }
}
