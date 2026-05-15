<?php

/**
 * API route definitions.
 *
 * Variables injected from public/index.php:
 *   $router   App\Lib\Router
 *   $auth     App\Controllers\AuthController
 *   $categories App\Controllers\CategoryController
 *   $products App\Controllers\ProductController
 *   $sales    App\Controllers\SaleController
 *   $admin    callable – middleware that requires admin authentication
 */

// Auth
$router->get('/api/auth/me', [$auth, 'current']);
$router->post('/api/auth/login', [$auth, 'login']);
$router->post('/api/auth/logout', [$auth, 'logout']);

// Categories
$router->get('/api/categories', [$categories, 'index']);
$router->post('/api/categories', $admin([$categories, 'store']));
$router->put('/api/categories/{id}', $admin([$categories, 'update']));
$router->delete('/api/categories/{id}', $admin([$categories, 'destroy']));

// Products
$router->get('/api/products', [$products, 'index']);
$router->get('/api/products/{id}', [$products, 'show']);
$router->post('/api/product-images', $admin([$products, 'uploadImage']));
$router->post('/api/products', $admin([$products, 'store']));
$router->put('/api/products/{id}', $admin([$products, 'update']));
$router->delete('/api/products/{id}', $admin([$products, 'destroy']));

// Sales
$router->get('/api/sales', $admin([$sales, 'index']));
$router->get('/api/sales/{id}', $admin([$sales, 'show']));
$router->post('/api/sales', [$sales, 'store']);
