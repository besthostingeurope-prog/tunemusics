import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {toast} from '@ui/toast/toast';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {useTrans} from '@ui/i18n/use-trans';
import {message} from '@ui/i18n/message';
import {ArtistPayout} from '@app/web-player/backstage/earnings/use-artist-payouts';

interface AdminPayoutsResponse extends BackendResponse {
  payouts: {
    data: ArtistPayout[];
    current_page: number;
    per_page: number;
    total: number;
  };
}

const ADMIN_PAYOUTS_KEY = ['admin', 'payouts'];

interface AdminPayoutsParams {
  status?: string;
  query?: string;
  page?: number;
  per_page?: number;
}

export function useAdminPayouts(params?: AdminPayoutsParams) {
  return useQuery({
    queryKey: [...ADMIN_PAYOUTS_KEY, params],
    queryFn: () => fetchPayouts(params),
  });
}

function fetchPayouts(params?: AdminPayoutsParams): Promise<AdminPayoutsResponse> {
  return apiClient
    .get('admin/payouts', {params})
    .then(response => response.data);
}

interface PayoutActionPayload {
  id: number;
  admin_notes?: string;
  rejection_reason?: string;
}

export function useApprovePayout() {
  const {trans} = useTrans();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PayoutActionPayload) => approvePayout(payload),
    onSuccess: () => {
      toast(trans(message('Payout approved successfully')));
      queryClient.invalidateQueries({queryKey: ADMIN_PAYOUTS_KEY});
    },
    onError: (err: any) =>
      toast.danger(
        err.response?.data?.message ||
          trans(message('Could not approve payout')),
      ),
  });
}

function approvePayout({id, admin_notes}: PayoutActionPayload) {
  return apiClient
    .post(`admin/payouts/${id}/approve`, {admin_notes})
    .then(r => r.data);
}

export function useRejectPayout() {
  const {trans} = useTrans();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PayoutActionPayload) => rejectPayout(payload),
    onSuccess: () => {
      toast(trans(message('Payout rejected successfully')));
      queryClient.invalidateQueries({queryKey: ADMIN_PAYOUTS_KEY});
    },
    onError: (err: any) =>
      toast.danger(
        err.response?.data?.message || trans(message('Could not reject payout')),
      ),
  });
}

function rejectPayout({id, rejection_reason, admin_notes}: PayoutActionPayload) {
  return apiClient
    .post(`admin/payouts/${id}/reject`, {rejection_reason, admin_notes})
    .then(r => r.data);
}

export function useMarkPayoutAsProcessing() {
  const {trans} = useTrans();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => markAsProcessing(id),
    onSuccess: () => {
      toast(trans(message('Payout marked as processing')));
      queryClient.invalidateQueries({queryKey: ADMIN_PAYOUTS_KEY});
    },
    onError: (err: any) =>
      toast.danger(
        err.response?.data?.message ||
          trans(message('Could not update payout status')),
      ),
  });
}

function markAsProcessing(id: number) {
  return apiClient.post(`admin/payouts/${id}/processing`).then(r => r.data);
}

export function useMarkPayoutAsCompleted() {
  const {trans} = useTrans();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => markAsCompleted(id),
    onSuccess: () => {
      toast(trans(message('Payout marked as completed')));
      queryClient.invalidateQueries({queryKey: ADMIN_PAYOUTS_KEY});
    },
    onError: (err: any) =>
      toast.danger(
        err.response?.data?.message ||
          trans(message('Could not complete payout')),
      ),
  });
}

function markAsCompleted(id: number) {
  return apiClient.post(`admin/payouts/${id}/complete`).then(r => r.data);
}

export function useMarkPayoutAsFailed() {
  const {trans} = useTrans();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PayoutActionPayload) => markAsFailed(payload),
    onSuccess: () => {
      toast(trans(message('Payout marked as failed')));
      queryClient.invalidateQueries({queryKey: ADMIN_PAYOUTS_KEY});
    },
    onError: (err: any) =>
      toast.danger(
        err.response?.data?.message ||
          trans(message('Could not update payout status')),
      ),
  });
}

function markAsFailed({id, admin_notes}: PayoutActionPayload) {
  return apiClient
    .post(`admin/payouts/${id}/failed`, {admin_notes})
    .then(r => r.data);
}
