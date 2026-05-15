<?php

use App\Controllers\CategoryController;
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Lib\Database;
use App\Lib\Http;
use App\Lib\Router;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Repositories\StaffRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

session_start();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Http::success(null);
}

set_exception_handler(function (Throwable $exception): void {
    Http::error('Internal server error.', 500, [
        'type' => $exception::class,
    ]);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pdo = Database::pdo();

$auth = new AuthController(new StaffRepository($pdo));
$categories = new CategoryController(new CategoryRepository($pdo));
$products = new ProductController(new ProductRepository($pdo));
$sales = new SaleController(new SaleRepository($pdo));
$router = new Router();

$admin = static function (callable $handler) use ($auth): callable {
    return static function (...$params) use ($auth, $handler): void {
        $auth->requireAdmin();
        $handler(...$params);
    };
};

$router->get('/api/auth/me', [$auth, 'current']);
$router->post('/api/auth/login', [$auth, 'login']);
$router->post('/api/auth/logout', [$auth, 'logout']);

$router->get('/api/categories', [$categories, 'index']);
$router->post('/api/categories', $admin([$categories, 'store']));
$router->put('/api/categories/{id}', $admin([$categories, 'update']));
$router->delete('/api/categories/{id}', $admin([$categories, 'destroy']));

$router->get('/api/products', [$products, 'index']);
$router->get('/api/products/{id}', [$products, 'show']);
$router->post('/api/product-images', $admin([$products, 'uploadImage']));
$router->post('/api/products', $admin([$products, 'store']));
$router->put('/api/products/{id}', $admin([$products, 'update']));
$router->delete('/api/products/{id}', $admin([$products, 'destroy']));

$router->get('/api/sales', $admin([$sales, 'index']));
$router->get('/api/sales/{id}', $admin([$sales, 'show']));
$router->post('/api/sales', [$sales, 'store']);

$router->dispatch($method, $path);
