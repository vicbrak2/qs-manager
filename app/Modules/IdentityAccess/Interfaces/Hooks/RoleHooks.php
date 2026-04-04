<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Interfaces\Hooks;

use QS\Core\Contracts\HookableInterface;
use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;

final class RoleHooks implements HookableInterface
{
    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('set_user_role', [$this, 'auditRoleChange'], 10, 3);
        }
    }

    /**
     * @param array<int, string> $oldRoles
     */
    public function auditRoleChange(int $userId, string $newRole, array $oldRoles): void
    {
        if (! in_array($newRole, QsRole::values(), true)) {
            return;
        }

        global $wpdb;

        if (! isset($wpdb)) {
            return;
        }

        $table = $wpdb->prefix . 'qs_audit_log';

        $wpdb->insert(
            $table,
            [
                'usuario_id' => get_current_user_id(),
                'accion' => 'identity_access.role_changed',
                'modulo' => 'IdentityAccess',
                'entidad_tipo' => 'wp_user',
                'entidad_id' => $userId,
                'datos_anteriores' => wp_json_encode(['roles' => $oldRoles]),
                'datos_nuevos' => wp_json_encode(['role' => $newRole]),
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }
}
