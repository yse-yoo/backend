<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\ProductRepository;

final class ProductController
{
    private const UPLOAD_MAX_SIZE = 2 * 1024 * 1024;
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(): void
    {
        Http::success($this->products->findAll([
            'include_inactive' => ($_GET['include_inactive'] ?? '') === '1',
            'category_id' => $_GET['category_id'] ?? null,
            'q' => $_GET['q'] ?? null,
        ]));
    }

    public function show(int $id): void
    {
        $product = $this->products->find($id);

        if ($product === null) {
            Http::error('Product not found.', 404);
        }

        Http::success($product);
    }

    public function store(): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        $id = $this->products->create($this->params($body));

        Http::success(['id' => $id], 201);
    }

    public function uploadImage(): void
    {
        if (!isset($_FILES['image'])) {
            Http::error('Image file is required.', 422, ['image' => 'Image file is required.']);
        }

        $file = $_FILES['image'];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Http::error('Image upload failed.', 422, ['image' => 'Image upload failed.']);
        }

        if (($file['size'] ?? 0) > self::UPLOAD_MAX_SIZE) {
            Http::error('Image must be 2MB or smaller.', 422, ['image' => 'Image must be 2MB or smaller.']);
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? finfo_file($finfo, $tmpName) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_string($mimeType) || !array_key_exists($mimeType, self::ALLOWED_IMAGE_MIMES)) {
            Http::error('Unsupported image type.', 422, ['image' => 'JPEG, PNG, GIF, or WebP images are supported.']);
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/products/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            Http::error('Failed to prepare upload directory.', 500);
        }

        $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED_IMAGE_MIMES[$mimeType];
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            Http::error('Failed to save image.', 500);
        }

        Http::success(['image_path' => 'uploads/products/' . $filename], 201);
    }

    public function update(int $id): void
    {
        $body = Http::jsonBody();
        $errors = $this->validate($body);

        if ($errors !== []) {
            Http::error('Validation failed.', 422, $errors);
        }

        $updated = $this->products->update($id, $this->params($body));

        if (!$updated && !$this->products->exists($id)) {
            Http::error('Resource not found.', 404);
        }

        Http::success(['id' => $id]);
    }

    public function destroy(int $id): void
    {
        if (!$this->products->exists($id)) {
            Http::error('Resource not found.', 404);
        }

        $this->products->softDelete($id);

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

        if (!isset($body['price']) || !is_numeric($body['price']) || (int)$body['price'] < 0) {
            $errors['price'] = 'Price must be zero or greater.';
        }

        if (isset($body['category_id']) && $body['category_id'] !== null && !is_numeric($body['category_id'])) {
            $errors['category_id'] = 'Category ID must be numeric or null.';
        }

        if (isset($body['tax_rate']) && (!is_numeric($body['tax_rate']) || (float)$body['tax_rate'] < 0)) {
            $errors['tax_rate'] = 'Tax rate must be zero or greater.';
        }

        if (isset($body['stock_quantity']) && $body['stock_quantity'] !== null && !is_numeric($body['stock_quantity'])) {
            $errors['stock_quantity'] = 'Stock quantity must be numeric or null.';
        }

        return $errors;
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function params(array $body): array
    {
        return [
            ':category_id' => isset($body['category_id']) && $body['category_id'] !== null ? (int)$body['category_id'] : null,
            ':name' => trim((string)$body['name']),
            ':price' => (int)$body['price'],
            ':tax_rate' => isset($body['tax_rate']) ? (float)$body['tax_rate'] : 10.0,
            ':tax_type' => (string)($body['tax_type'] ?? 'standard'),
            ':icon' => isset($body['icon']) && $body['icon'] !== '' ? (string)$body['icon'] : null,
            ':image_path' => isset($body['image_path']) && $body['image_path'] !== '' ? (string)$body['image_path'] : null,
            ':stock_quantity' => isset($body['stock_quantity']) && $body['stock_quantity'] !== null ? (int)$body['stock_quantity'] : null,
            ':is_active' => isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1,
            ':display_order' => isset($body['display_order']) ? (int)$body['display_order'] : 0,
        ];
    }
}
