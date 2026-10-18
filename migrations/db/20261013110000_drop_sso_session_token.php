<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * SSO : suppression de sso_sessions.session_token (jamais lu ; les sessions TrustedX sont fermées
 * à la déconnexion MONARC par l'utilisateur, voir DELETE /api/sso/session).
 */
class DropSsoSessionToken extends AbstractMigration
{
    public function up(): void
    {
        // L'index sur user_id doit exister avant de retirer l'ancien : il porte la clé étrangère vers users.
        $this->table('sso_sessions')
            ->addIndex(['user_id'], ['name' => 'idx_sso_user'])
            ->update();

        $this->table('sso_sessions')
            ->removeIndexByName('idx_sso_user_session')
            ->removeColumn('session_token')
            ->update();
    }

    public function down(): void
    {
        $this->table('sso_sessions')
            ->addColumn('session_token', 'string', ['limit' => 100, 'null' => false, 'default' => '', 'after' => 'provider_id'])
            ->addIndex(['user_id', 'session_token'], ['name' => 'idx_sso_user_session'])
            ->update();

        $this->table('sso_sessions')
            ->removeIndexByName('idx_sso_user')
            ->update();
    }
}
