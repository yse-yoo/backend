<?php

use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Lib\Database;
use App\Lib\Http;
use App\Lib\Router;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

header('Access-Control-Allow-Origin: *');
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

$categories = new CategoryController(new CategoryRepository($pdo));
$products = new ProductController(new ProductRepository($pdo));
$sales = new SaleController(new SaleRepository($pdo));
$router = new Router();

$router->get('/api/categories', [$categories, 'index']);
$router->post('/api/categories', [$categories, 'store']);
$router->put('/api/categories/{id}', [$categories, 'update']);
$router->delete('/api/categories/{id}', [$categories, 'destroy']);

$router->get('/api/products', [$products, 'index']);
$router->get('/api/products/{id}', [$products, 'show']);
$router->post('/api/products', [$products, 'store']);
$router->put('/api/products/{id}', [$products, 'update']);
$router->delete('/api/products/{id}', [$products, 'destroy']);

$router->get('/api/sales', [$sales, 'index']);
$router->get('/api/sales/{id}', [$sales, 'show']);
$router->post('/api/sales', [$sales, 'store']);

$router->dispatch($method, $path);
