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
 *   $checkoutRequests App\Controllers\CheckoutRequestController
 *   $orderDrafts App\Controllers\OrderDraftController
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
$router->get('/api/sales/analytics', $admin([$analytics, 'index']));
$router->get('/api/sales/{id}', $admin([$sales, 'show']));

// Payments（全決済の入口）
$router->post('/api/payments/square/checkout', [$square, 'checkout']);

// Checkout requests
$router->get('/api/checkout-requests/current', [$checkoutRequests, 'current']);
$router->get('/api/checkout-requests/{checkout_id}', [$checkoutRequests, 'show']);
$router->post('/api/checkout-requests', $admin([$checkoutRequests, 'store']));
$router->post('/api/checkout-requests/{checkout_id}/complete', [$checkoutRequests, 'complete']);
$router->delete('/api/checkout-requests/{checkout_id}', $admin([$checkoutRequests, 'cancel']));

// Current order draft
$router->get('/api/order-draft/stream', [$orderDrafts, 'stream']);
$router->get('/api/order-draft/current', [$orderDrafts, 'current']);
$router->put('/api/order-draft/current', $admin([$orderDrafts, 'save']));
$router->delete('/api/order-draft/current', $admin([$orderDrafts, 'clear']));
