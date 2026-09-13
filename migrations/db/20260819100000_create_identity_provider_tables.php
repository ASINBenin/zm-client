<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateIdentityProviderTables extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('providers')) {
            $tableProviders = $this->table('providers');
            $tableProviders->addColumn('code', 'string', ['limit' => 50, 'null' => false])
                           ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                           ->addColumn('is_active', 'boolean', ['default' => true])
                           ->addColumn('creator', 'string', ['limit' => 255, 'null' => true])
                           ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
                           ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                           ->addColumn('updated_at', 'datetime', ['null' => true])
                           ->addIndex(['code'], ['unique' => true])
                           ->create();

            $this->execute("INSERT INTO providers (code, name, is_active, creator) VALUES ('trustedx_pki', 'Identité Numérique PKI (TrustedX)', 1, 'System')");
        }

        if (!$this->hasTable('identities')) {
            $tableIdentities = $this->table('identities');
            $tableIdentities->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
                            ->addColumn('provider_id', 'integer', ['signed' => false, 'null' => false])
                            ->addColumn('provider_identifier', 'string', ['limit' => 100, 'null' => false])
                            ->addColumn('sub', 'string', ['limit' => 255, 'null' => true])
                            ->addColumn('extra_data', 'text', ['null' => true])
                            ->addColumn('creator', 'string', ['limit' => 255, 'null' => true])
                            ->addColumn('updater', 'string', ['limit' => 255, 'null' => true])
                            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                            ->addColumn('updated_at', 'datetime', ['null' => true])
                            ->addIndex(['provider_id', 'provider_identifier'], ['unique' => true, 'name' => 'uk_provider_identifier'])
                            ->addIndex(['user_id', 'provider_id'], ['unique' => true, 'name' => 'uniq_user_provider'])
                            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                            ->addForeignKey('provider_id', 'providers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                            ->create();
        }

        if (!$this->hasTable('sso_sessions')) {
            $table = $this->table('sso_sessions');
            $table->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('provider_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('session_token', 'string', ['limit' => 100, 'null' => false])
                  ->addColumn('access_token_encrypted', 'text', ['null' => false])
                  ->addColumn('refresh_token_encrypted', 'text', ['null' => true])
                  ->addColumn('id_token_encrypted', 'text', ['null' => true])
                  ->addColumn('token_type', 'string', ['limit' => 50, 'default' => 'Bearer'])
                  ->addColumn('scope', 'text', ['null' => true])
                  ->addColumn('expires_at', 'datetime', ['null' => true])
                  ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                  ->addIndex(['user_id', 'session_token'], ['name' => 'idx_sso_user_session'])
                  ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->addForeignKey('provider_id', 'providers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->create();
        }
    }
}
