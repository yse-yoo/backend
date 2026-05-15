<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\StaffRepository;

final class AuthController
{
    public function __construct(private readonly StaffRepository $staffs)
    {
    }

    public function current(): void
    {
        $staffId = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 0;
        if ($staffId <= 0) {
            Http::success(null);
        }

        $staff = $this->staffs->findActiveById($staffId);
        if ($staff === null || ($staff['role'] ?? '') !== 'admin') {
            unset($_SESSION['staff_id']);
            Http::success(null);
        }

        Http::success($this->responseStaff($staff));
    }

    public function login(): void
    {
        $body = Http::jsonBody();
        $loginId = trim((string)($body['login_id'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($loginId === '' || $password === '') {
            Http::error('ログインIDとパスワードを入力してください。', 422);
        }

        $staff = $this->staffs->findActiveByLoginId($loginId);
        if ($staff === null || ($staff['role'] ?? '') !== 'admin' || !password_verify($password, (string)$staff['password_hash'])) {
            Http::error('ログインIDまたはパスワードが正しくありません。', 401);
        }

        session_regenerate_id(true);
        $_SESSION['staff_id'] = (int)$staff['id'];

        Http::success($this->responseStaff($staff));
    }

    public function logout(): void
    {
        unset($_SESSION['staff_id']);
        Http::success(null);
    }

    public function requireAdmin(): void
    {
        $staffId = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 0;
        if ($staffId <= 0) {
            Http::error('管理者ログインが必要です。', 401);
        }

        $staff = $this->staffs->findActiveById($staffId);
        if ($staff === null || ($staff['role'] ?? '') !== 'admin') {
            unset($_SESSION['staff_id']);
            Http::error('管理者ログインが必要です。', 401);
        }
    }

    private function responseStaff(array $staff): array
    {
        return [
            'id' => (int)$staff['id'],
            'login_id' => (string)$staff['login_id'],
            'name' => (string)$staff['name'],
            'role' => (string)$staff['role'],
        ];
    }
}
