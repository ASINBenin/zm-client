<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateSignatureTables extends AbstractMigration
{
    public function change(): void
    {
        // 1. Table des demandes globales de signature (signature_requests)
        if (!$this->hasTable('signature_requests')) {
            $table = $this->table('signature_requests');
            $table->addColumn('anr_id', 'integer', ['null' => true, 'signed' => false])
                  ->addColumn('deliverable_doc_type', 'integer', ['default' => 1, 'signed' => false])
                  ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
                  ->addColumn('file_original', 'string', ['limit' => 500, 'null' => false])
                  ->addColumn('file_current', 'string', ['limit' => 500, 'null' => false])
                  ->addColumn('file_signed', 'string', ['limit' => 500, 'null' => true])
                  ->addColumn('rejection_reason', 'text', ['null' => true])
                  ->addColumn('status', 'string', ['limit' => 50, 'default' => 'draft'])
                  ->addColumn('current_step', 'integer', ['default' => 1])
                  ->addColumn('creator', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addIndex(['anr_id'])
                  ->addIndex(['status'])
                  ->addForeignKey('anr_id', 'anrs', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                  ->create();
        }

        // 2. Table des signataires associés (signature_signatories)
        if (!$this->hasTable('signature_signatories')) {
            $table = $this->table('signature_signatories');
            $table->addColumn('request_id', 'integer', ['null' => false, 'signed' => false])
                  ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
                  ->addColumn('order_index', 'integer', ['default' => 1])
                  ->addColumn('status', 'string', ['limit' => 50, 'default' => 'pending'])
                  ->addColumn('page_number', 'integer', ['default' => 1])
                  ->addColumn('coord_x', 'float', ['default' => 0])
                  ->addColumn('coord_y', 'float', ['default' => 0])
                  ->addColumn('width', 'float', ['default' => 150])
                  ->addColumn('height', 'float', ['default' => 60])
                  ->addColumn('signed_at', 'datetime', ['null' => true])
                  ->addColumn('signature_tx_id', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('certificate_subject', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('rejection_reason', 'text', ['null' => true])
                  ->addColumn('creator', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
                  ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addIndex(['request_id'])
                  ->addIndex(['user_id'])
                  ->addForeignKey('request_id', 'signature_requests', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                  ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
                  ->create();
        }
    }
}
