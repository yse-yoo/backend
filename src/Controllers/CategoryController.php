<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\CategoryRepository;

final class CategoryController
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function index(): void
    {
        Http::success(
            $this->categories->findAll(($_GET['include_inactive'] ?? '') === '1')
        );
    }

    public function store(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        $id = $this->categories->create($this->params($body));

        Http::success(['id' => $id], 201);
    }

    public function update(int $id): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        $updated = $this->categories->update($id, $this->params($body));

        if (!$updated && !$this->categories->exists($id)) {
            Http::error('Resource not found.', 404);
        }

        Http::success(['id' => $id]);
    }

    public function destroy(int $id): void
    {
        if (!$this->categories->exists($id)) {
            Http::error('Resource not found.', 404);
        }

        $this->categories->softDelete($id);

        Http::success(['id' => $id]);
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $body): array
    {
        $errors = [];

        if (!isset($body['name']) || trim((string)$body['name']) === '') {
            $errors['name'] = 'Name is required.';
        }

        if (isset($body['display_order']) && !is_numeric($body['display_order'])) {
            $errors['display_order'] = 'Display order must be numeric.';
        }

        return $errors;
    }

    /**
     * @return array<string, int|string>
     */
    private function params(array $body): array
    {
        return [
            ':name' => trim((string)$body['name']),
            ':display_order' => (int)($body['display_order'] ?? 0),
            ':is_active' => isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1,
        ];
    }
}
