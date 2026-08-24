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
                           ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                           ->addIndex(['code'], ['unique' => true])
                           ->create();

            $this->execute("INSERT INTO providers (code, name, is_active) VALUES ('trustedx_pki', 'Identité Numérique PKI (TrustedX)', 1)");
        }

        if (!$this->hasTable('identities')) {
            $tableIdentities = $this->table('identities');
            $tableIdentities->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
                            ->addColumn('provider_id', 'integer', ['signed' => false, 'null' => false])
                            ->addColumn('provider_identifier', 'string', ['limit' => 100, 'null' => false, 'comment' => 'NPI / NIP ou ID distant'])
                            ->addColumn('sub', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Subject Identifier OIDC / TrustedX (sub)'])
                            ->addColumn('extra_data', 'text', ['null' => true, 'comment' => 'Métadonnées JSON certifiées'])
                            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                            ->addIndex(['provider_id', 'provider_identifier'], ['unique' => true, 'name' => 'uk_provider_identifier'])
                            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                            ->addForeignKey('provider_id', 'providers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                            ->create();
        }
    }
}
