import { BackendResponse } from '@common/http/backend-response/backend-response';
import { apiClient } from '@common/http/query-client';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { message } from '@ui/i18n/message';
import { toast } from '@ui/toast/toast';

export interface PeriodEarning {
  period: string;
  gross: number;
  net: number;
  followers_at_period: number;
}

export interface ArtistEarning {
  artist_id: number;
  artist_name: string;
  user_email?: string | null;
  product_name?: string;
  followers: number;
  per_follower: number;
  gross: number;
  net: number;
  pending_gross?: number;
  pending_net?: number;
  fee: number;
  has_payout_method: boolean;
  bank_details?: {
    type?: string;
    account_holder_name?: string;
    bank_name?: string;
    iban?: string;
    account_number?: string;
    routing_number?: string;
    swift_code?: string;
    country?: string;
    paypal_email?: string;
  } | null;
  status?: 'pending' | 'accrued' | 'none';
  paid_today?: boolean;
  periods?: PeriodEarning[];
}

interface ArtistEarningsResponse extends BackendResponse {
  earnings: ArtistEarning[];
}

export function useArtistEarnings() {
  return useQuery({
    queryKey: ['admin', 'artist-earnings'],
    queryFn: () => fetchArtistEarnings(),
  });
}

function fetchArtistEarnings(): Promise<ArtistEarningsResponse> {
  return apiClient
    .get('admin/artist-earnings')
    .then(response => response.data);
}

interface CalculateEarningsPayload {
  date?: string;
  period?: string;
}

interface CalculateEarningsResponse extends BackendResponse {
  message: string;
  date: string;
  output: string;
}

export function useCalculateEarnings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload?: CalculateEarningsPayload) => {
      return apiClient
        .post<CalculateEarningsResponse>('admin/artist-earnings/calculate', payload)
        .then(response => response.data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({queryKey: ['admin', 'artist-earnings']});
      toast(message('Earnings calculation completed successfully'));
    },
    onError: () => {
      toast.danger(message('Failed to calculate earnings'));
    },
  });
}

