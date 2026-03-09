<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\DoughnutChartWidget;

/**
 * Dashboard-Widget: Abo-Verteilung als Doughnut-Chart.
 */
class SubscriptionChart extends DoughnutChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.subscription_chart');
    }

    protected function getData(): array
    {
        $statusLabels = __('admin.tenant.subscription_statuses');
        $statusColors = [
            'trial' => '#f59e0b',     // Amber
            'active' => '#10b981',    // Emerald
            'expired' => '#f43f5e',   // Rose
            'cancelled' => '#6b7280', // Gray
        ];

        $data = Tenant::selectRaw('subscription_status, COUNT(*) as count')
            ->groupBy('subscription_status')
            ->pluck('count', 'subscription_status')
            ->toArray();

        // Immer alle Status zeigen (auch wenn 0)
        $allStatuses = ['trial', 'active', 'expired', 'cancelled'];
        $values = [];
        $labels = [];
        $colors = [];

        foreach ($allStatuses as $status) {
            $values[] = $data[$status] ?? 0;
            $labels[] = $statusLabels[$status] ?? $status;
            $colors[] = $statusColors[$status] ?? '#9ca3af';
        }

        return [
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
