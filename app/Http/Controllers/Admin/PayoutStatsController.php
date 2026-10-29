<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Common\Billing\Models\Product;
use Common\Billing\Subscription;
use Common\Core\BaseController;
use Illuminate\Support\Facades\DB;

class PayoutStatsController extends BaseController
{
    public function index()
    {
        // Get active subscriptions count (either not cancelled OR on grace period)
        $activeSubscriptions = Subscription::where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->count();

        // Get total unique subscribers
        $totalSubscribers = Subscription::where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->distinct('user_id')
            ->count('user_id');

        // Calculate monthly revenue from active subscriptions (MRR) - convert to cents
        $monthlyRevenue = Subscription::where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->join('prices', 'subscriptions.price_id', '=', 'prices.id')
            ->sum(DB::raw('CASE
                WHEN prices.interval = "month" THEN (prices.amount / prices.interval_count * 100)
                WHEN prices.interval = "year" THEN (prices.amount / prices.interval_count / 12 * 100)
                ELSE 0
            END'));

        // Get new subscriptions in last 30 days
        $newSubscriptions30d = Subscription::where('created_at', '>=', now()->subDays(30))
            ->count();

        // Get revenue by plan (MRR) - convert to cents
        $revenueByPlan = Product::select([
                'products.name',
                'products.id',
                DB::raw('COUNT(subscriptions.id) as subscriber_count'),
                DB::raw('SUM(CASE
                    WHEN prices.interval = "month" THEN (prices.amount / prices.interval_count * 100)
                    WHEN prices.interval = "year" THEN (prices.amount / prices.interval_count / 12 * 100)
                    ELSE 0
                END) as revenue'),
                DB::raw('MIN(CASE
                    WHEN prices.interval = "month" THEN (prices.amount / prices.interval_count * 100)
                    WHEN prices.interval = "year" THEN (prices.amount / prices.interval_count / 12 * 100)
                    ELSE 0
                END) as price')
            ])
            ->leftJoin('subscriptions', function ($join) {
                $join->on('products.id', '=', 'subscriptions.product_id')
                    ->where(function ($query) {
                        $query->whereNull('subscriptions.ends_at')
                            ->orWhere('subscriptions.ends_at', '>', now());
                    });
            })
            ->leftJoin('prices', 'subscriptions.price_id', '=', 'prices.id')
            ->groupBy('products.id', 'products.name')
            ->having('subscriber_count', '>', 0)
            ->get();

        // Get top paying users (by yearly value) - convert to cents
        $topPayingUsers = User::select([
                'users.id',
                'users.email',
                'users.name',
                'users.image as avatar',
                'products.name as plan_name',
                DB::raw('CASE
                    WHEN prices.interval = "month" THEN (prices.amount / prices.interval_count * 12 * 100)
                    WHEN prices.interval = "year" THEN (prices.amount / prices.interval_count * 100)
                    ELSE 0
                END as yearly_value'),
                DB::raw('CASE
                    WHEN prices.interval = "month" THEN (prices.amount / prices.interval_count * 100)
                    WHEN prices.interval = "year" THEN (prices.amount / prices.interval_count / 12 * 100)
                    ELSE 0
                END as monthly_price')
            ])
            ->join('subscriptions', 'users.id', '=', 'subscriptions.user_id')
            ->join('products', 'subscriptions.product_id', '=', 'products.id')
            ->join('prices', 'subscriptions.price_id', '=', 'prices.id')
            ->where(function ($query) {
                $query->whereNull('subscriptions.ends_at')
                    ->orWhere('subscriptions.ends_at', '>', now());
            })
            ->orderByDesc('yearly_value')
            ->limit(10)
            ->get();

        // Get subscriptions by gateway
        $subscriptionsByGateway = Subscription::select([
                'gateway_name as gateway',
                DB::raw('COUNT(*) as count')
            ])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->groupBy('gateway_name')
            ->get();

        return $this->success([
            'monthly_revenue' => (int) $monthlyRevenue,
            'active_subscriptions' => $activeSubscriptions,
            'total_subscribers' => $totalSubscribers,
            'new_subscriptions_30d' => $newSubscriptions30d,
            'revenue_by_plan' => $revenueByPlan->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'subscriber_count' => (int) $plan->subscriber_count,
                    'revenue' => (int) ($plan->revenue ?? 0),
                    'price' => (int) ($plan->price ?? 0),
                ];
            }),
            'top_paying_users' => $topPayingUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'plan_name' => $user->plan_name,
                    'yearly_value' => (int) ($user->yearly_value ?? 0),
                    'monthly_price' => (int) ($user->monthly_price ?? 0),
                ];
            }),
            'subscriptions_by_gateway' => $subscriptionsByGateway,
        ]);
    }
}
