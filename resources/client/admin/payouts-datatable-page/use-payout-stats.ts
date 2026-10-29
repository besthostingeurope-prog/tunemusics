import {useQuery} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {BackendResponse} from '@common/http/backend-response/backend-response';

export interface PayoutStats {
  monthly_revenue: number;
  active_subscriptions: number;
  total_subscribers: number;
  new_subscriptions_30d: number;
  revenue_by_plan: RevenueByPlan[];
  top_paying_users: TopPayingUser[];
  subscriptions_by_gateway: SubscriptionsByGateway[];
}

export interface RevenueByPlan {
  id: number;
  name: string;
  subscriber_count: number;
  revenue: number;
}

export interface TopPayingUser {
  id: number;
  email: string;
  name: string;
  avatar: string | null;
  plan_name: string;
  yearly_value: number;
}

export interface SubscriptionsByGateway {
  gateway: string;
  count: number;
}

interface PayoutStatsResponse extends BackendResponse {
  monthly_revenue: number;
  active_subscriptions: number;
  total_subscribers: number;
  new_subscriptions_30d: number;
  revenue_by_plan: RevenueByPlan[];
  top_paying_users: TopPayingUser[];
  subscriptions_by_gateway: SubscriptionsByGateway[];
}

export function usePayoutStats() {
  return useQuery({
    queryKey: ['admin', 'payout-stats'],
    queryFn: () => fetchPayoutStats(),
  });
}

function fetchPayoutStats(): Promise<PayoutStatsResponse> {
  return apiClient
    .get('admin/payout-stats')
    .then(response => response.data);
}
