<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Infrastructure\Persistence;

use QS\Modules\IdentityAccess\Domain\Entity\QsUser;
use QS\Modules\IdentityAccess\Domain\Policy\AccessPolicy;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;
use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;
use QS\Modules\IdentityAccess\Domain\ValueObject\UserId;

final class WpUserRepository implements UserRepository
{
    public function __construct(private readonly AccessPolicy $accessPolicy)
    {
    }

    public function findById(int $userId): ?QsUser
    {
        if (! function_exists('get_userdata')) {
            return null;
        }

        $user = get_userdata($userId);

        if (! $user instanceof \WP_User) {
            return null;
        }

        $qsRole = null;

        foreach ($user->roles as $role) {
            $qsRole = QsRole::fromWordPressRole((string) $role);

            if ($qsRole !== null) {
                break;
            }
        }

        $qsUser = new QsUser(
            new UserId((int) $user->ID),
            (string) $user->user_email,
            (string) $user->display_name,
            array_values(array_map('strval', $user->roles)),
            [],
            $qsRole
        );

        return new QsUser(
            $qsUser->id(),
            $qsUser->email(),
            $qsUser->displayName(),
            $qsUser->wordpressRoles(),
            $this->derivePermissions($qsUser),
            $qsUser->qsRole()
        );
    }

    public function assignRole(int $userId, QsRole $role): void
    {
        if (! class_exists(\WP_User::class)) {
            return;
        }

        $user = new \WP_User($userId);

        foreach (QsRole::values() as $qsRole) {
            if (in_array($qsRole, $user->roles, true)) {
                $user->remove_role($qsRole);
            }
        }

        $user->add_role($role->value);
    }

    public function permissionsFor(int $userId): array
    {
        $user = $this->findById($userId);

        if ($user === null) {
            return [];
        }

        return $this->derivePermissions($user);
    }

    /**
     * @return array<int, string>
     */
    private function derivePermissions(QsUser $user): array
    {
        $permissions = [];
        $candidateCapabilities = [
            'read',
            'qs_manage_staff',
            'qs_view_finance',
            'qs_manage_bitacoras',
            'qs_manage_bookings',
            'qs_manage_content_qa',
            'qs_manage_agents',
        ];

        foreach ($candidateCapabilities as $capability) {
            if ($this->accessPolicy->allows($user, $capability)) {
                $permissions[] = $capability;
            }
        }

        return $permissions;
    }
}
