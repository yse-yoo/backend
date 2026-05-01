<?php

namespace App\Lib;

final class Http
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200): void
    {
        self::json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $details = []): void
    {
        $error = ['message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        self::json([
            'success' => false,
            'error' => $error,
        ], $status);
    }

    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $body = json_decode($raw, true);

        if (!is_array($body)) {
            self::error('Invalid JSON body.', 400);
        }

        return $body;
    }
}
