<?php

namespace App\Repositories;

use PDO;

final class StaffRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveByLoginId(string $loginId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login_id, name, password_hash, role, is_active
             FROM staffs
             WHERE login_id = :login_id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':login_id' => $loginId]);
        $staff = $stmt->fetch();

        return $staff ?: null;
    }

    public function findActiveById(int $staffId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, login_id, name, role, is_active
             FROM staffs
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $staffId]);
        $staff = $stmt->fetch();

        return $staff ?: null;
    }
}
