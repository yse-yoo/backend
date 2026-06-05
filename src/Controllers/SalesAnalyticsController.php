<?php

namespace App\Controllers;

use App\Lib\Http;
use App\Repositories\SaleRepository;

final class SalesAnalyticsController
{
    public function __construct(private readonly SaleRepository $sales)
    {
    }

    public function index(): void
    {
        $period   = $_GET['period']    ?? 'daily';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            Http::error('Invalid period. Must be daily, weekly, or monthly.', 422);
        }

        Http::success($this->sales->findAnalytics($period, $dateFrom, $dateTo));
    }
}
