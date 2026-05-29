<?php

use App\Controllers\CategoryController;
use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\SquareController;
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
$saleRepository = new SaleRepository($pdo);
$sales = new SaleController($saleRepository);
$square = new SquareController($saleRepository);
$router = new Router();

$admin = static function (callable $handler) use ($auth): callable {
    return static function (...$params) use ($auth, $handler): void {
        $auth->requireAdmin();
        $handler(...$params);
    };
};

require dirname(__DIR__) . '/routes/api.php';

$router->dispatch($method, $path);
