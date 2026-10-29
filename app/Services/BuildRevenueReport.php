<?php

namespace App\Services;

use Common\Billing\Invoices\Invoice;
use Common\Billing\Models\Product;
use Common\Database\Metrics\MetricDateRange;
use Common\Database\Metrics\Partition;
use Common\Database\Metrics\Trend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BuildRevenueReport
{
    protected Builder $builder;
    protected array $params = [];
    protected MetricDateRange $dateRange;

    public function execute(array $params): array
    {
        Gate::authorize('admin.access');

        $this->params = $params;
        $this->builder = $this->createBuilder();

        $this->dateRange = new MetricDateRange(
            start: $this->params['startDate'] ?? null,
            end: $this->params['endDate'] ?? null,
            timezone: $this->params['timezone'] ?? null,
        );

        $metrics = explode(',', Arr::get($params, 'metrics', 'revenue'));

        return collect($metrics)
            ->mapWithKeys(function ($metric) {
                $method = sprintf('get%sMetric', ucfirst($metric));
                if (method_exists($this, $method)) {
                    return [$metric => $this->$method()];
                }
                return [$metric => []];
            })
            ->toArray();
    }

    protected function createBuilder(): Builder
    {
        // Revenue report is based on paid invoices
        return Invoice::query()->where('status', 'paid');
    }

    protected function getRevenueMetric(): array
    {
        $trend = new Trend($this->builder, dateRange: $this->dateRange);
        $data = $trend->sum('amount_paid');

        return [
            'granularity' => $this->dateRange->granularity,
            'total' => array_sum(Arr::pluck($data, 'value')),
            'datasets' => [
                [
                    'label' => __('Revenue'),
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getMrrMetric(): array
    {
        // Monthly Recurring Revenue - calculate average subscription value per month
        $trend = new Trend($this->builder, dateRange: $this->dateRange);
        $data = $trend->average('amount_paid');

        return [
            'granularity' => $this->dateRange->granularity,
            'total' => array_sum(Arr::pluck($data, 'value')),
            'datasets' => [
                [
                    'label' => __('MRR'),
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getProductsMetric(): array
    {
        $data = (new Partition(
            $this->builder->orderBy('aggregate', 'desc'),
            groupBy: 'subscription_id',
            dateRange: $this->dateRange,
            limit: 30,
        ))->sum('amount_paid');

        // Get subscription product info
        $subscriptionIds = collect($data)->pluck('label')->filter()->unique();
        $subscriptions = DB::table('subscriptions')
            ->whereIn('id', $subscriptionIds)
            ->select('id', 'product_id')
            ->get()
            ->keyBy('id');

        $productIds = $subscriptions->pluck('product_id')->filter()->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $groupedByProduct = [];
        foreach ($data as $item) {
            $subscription = $subscriptions->get($item['label']);
            if ($subscription) {
                $productId = $subscription->product_id;
                if (!isset($groupedByProduct[$productId])) {
                    $groupedByProduct[$productId] = [
                        'value' => 0,
                        'label' => $productId,
                    ];
                }
                $groupedByProduct[$productId]['value'] += $item['value'];
            }
        }

        $data = collect($groupedByProduct)
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->map(function ($item) use ($products) {
                $product = $products->get($item['label']);
                if ($product) {
                    $item['model'] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                    ];
                    $item['label'] = $product->name;
                }
                return $item;
            })
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => __('Revenue'),
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getGatewaysMetric(): array
    {
        $data = (new Partition(
            $this->builder
                ->join('subscriptions', 'invoices.subscription_id', '=', 'subscriptions.id')
                ->orderBy('aggregate', 'desc'),
            groupBy: 'gateway_name',
            dateRange: $this->dateRange,
            limit: 10,
        ))->sum('amount_paid');

        $data = array_map(function ($item) {
            // Capitalize gateway names for display
            $item['label'] = ucfirst($item['label'] ?? 'Unknown');
            return $item;
        }, $data);

        return [
            'datasets' => [
                [
                    'label' => __('Revenue'),
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getSubscriptionsMetric(): array
    {
        $trend = new Trend($this->builder, dateRange: $this->dateRange);
        $data = $trend->count();

        return [
            'granularity' => $this->dateRange->granularity,
            'total' => array_sum(Arr::pluck($data, 'value')),
            'datasets' => [
                [
                    'label' => __('Subscriptions'),
                    'data' => $data,
                ],
            ],
        ];
    }
}
