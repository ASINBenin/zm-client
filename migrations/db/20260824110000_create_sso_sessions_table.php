<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateSsoSessionsTable extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('sso_sessions')) {
            $table = $this->table('sso_sessions');
            $table->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('provider_id', 'integer', ['signed' => false, 'null' => false])
                  ->addColumn('session_token', 'string', ['limit' => 100, 'null' => false, 'comment' => 'Jeton de session MONARC'])
                  ->addColumn('access_token_encrypted', 'text', ['null' => false, 'comment' => 'Access token chiffré en AES-256-GCM'])
                  ->addColumn('refresh_token_encrypted', 'text', ['null' => true, 'comment' => 'Refresh token chiffré en AES-256-GCM'])
                  ->addColumn('id_token_encrypted', 'text', ['null' => true, 'comment' => 'ID token chiffré'])
                  ->addColumn('token_type', 'string', ['limit' => 50, 'default' => 'Bearer'])
                  ->addColumn('scope', 'text', ['null' => true])
                  ->addColumn('expires_at', 'datetime', ['null' => true, 'comment' => 'Date d\'expiration du jeton'])
                  ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                  ->addIndex(['user_id', 'session_token'], ['name' => 'idx_sso_user_session'])
                  ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->addForeignKey('provider_id', 'providers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                  ->create();
        }
    }
}