import { ArtistPayout } from '@app/web-player/backstage/earnings/use-artist-payouts';
import { StaticPageTitle } from '@common/seo/static-page-title';
import { Button } from '@ui/buttons/button';
import { Chip } from '@ui/forms/input-field/chip-field/chip';
import { TextField } from '@ui/forms/input-field/text-field/text-field';
import { Option, Select } from '@ui/forms/select/select';
import { FormattedCurrency } from '@ui/i18n/formatted-currency';
import { FormattedDate } from '@ui/i18n/formatted-date';
import { Trans } from '@ui/i18n/trans';
import { Skeleton } from '@ui/skeleton/skeleton';
import { Dialog } from '@ui/overlays/dialog/dialog';
import { DialogBody } from '@ui/overlays/dialog/dialog-body';
import { DialogHeader } from '@ui/overlays/dialog/dialog-header';
import { DialogTrigger } from '@ui/overlays/dialog/dialog-trigger';
import { Tooltip } from '@ui/tooltip/tooltip';
import React, { useState } from 'react';
import { ArtistEarning } from './use-artist-earnings';
import {
  useAdminPayouts,
  useApprovePayout,
  useMarkPayoutAsCompleted,
  useRejectPayout,
} from './use-admin-payouts';
import { useArtistEarnings, useCalculateEarnings } from './use-artist-earnings';
import { usePayoutStats } from './use-payout-stats';

export function Component() {
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');

  const statsQuery = usePayoutStats();
  const earningsQuery = useArtistEarnings();
  const payoutsQuery = useAdminPayouts({
    status: statusFilter === 'all' ? undefined : statusFilter,
    query: searchQuery || undefined,
  });

  return (
    <div className="p-24 min-h-screen">
      <StaticPageTitle>
        <Trans message="Artist Payouts" />
      </StaticPageTitle>

      <h1 className="mb-24 text-3xl font-bold">
        <Trans message="Artist Payouts" />
      </h1>

      {/* User Payments Stats */}
      <UserPaymentsStatsSection query={statsQuery} />

      {/* Artist Accrued Earnings */}
      <ArtistAccruedEarningsSection query={earningsQuery} />

      {/* Artist Payout Requests */}
      <ArtistPayoutRequestsSection
        query={payoutsQuery}
        statusFilter={statusFilter}
        setStatusFilter={setStatusFilter}
        searchQuery={searchQuery}
        setSearchQuery={setSearchQuery}
      />
    </div>
  );
}

interface UserPaymentsStatsSectionProps {
  query: ReturnType<typeof usePayoutStats>;
}

function UserPaymentsStatsSection({query}: UserPaymentsStatsSectionProps) {
  const calculateEarnings = useCalculateEarnings();

  if (query.isPending) {
    return (
      <div className="mb-40">
        <div className="flex items-center justify-between mb-16">
          <h2 className="text-xl font-semibold">
            <Trans message="User Payments" />
          </h2>
        </div>
        <p className="text-sm text-muted mb-24">
          <Trans message="Overview of subscription revenue and top paying users" />
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-16 mb-32">
          {[...Array(4)].map((_, i) => (
            <Skeleton key={i} variant="rect" size="h-96" />
          ))}
        </div>
      </div>
    );
  }

  const stats = query.data;

  return (
    <div className="mb-40">
      <div className="flex items-center justify-between mb-16">
        <h2 className="text-xl font-semibold">
          <Trans message="User Payments" />
        </h2>
        <div className="flex gap-8">
          <Button
            variant="flat"
            color="primary"
            size="sm"
            onClick={() => calculateEarnings.mutate({date: new Date().toISOString().split('T')[0]})}
            disabled={calculateEarnings.isPending}
          >
            <Trans message="Calculate Daily (Analytics)" />
          </Button>
          <Button
            variant="flat"
            color="primary"
            size="sm"
            onClick={() => {
              const lastMonth = new Date();
              lastMonth.setMonth(lastMonth.getMonth() - 1);
              calculateEarnings.mutate({period: lastMonth.toISOString().slice(0, 7)});
            }}
            disabled={calculateEarnings.isPending}
          >
            <Trans message="Calculate Monthly Accrual" />
          </Button>
        </div>
      </div>
      <p className="text-sm text-muted mb-24">
        <Trans message="Overview of subscription revenue and top paying users" />
      </p>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-16 mb-32">
        <StatCard
          label={<Trans message="Monthly Revenue" />}
          value={<FormattedCurrency value={(stats?.monthly_revenue ?? 0) / 100} currency="USD" />}
          icon="💰"
        />
        <StatCard
          label={<Trans message="Active Subscriptions" />}
          value={stats?.active_subscriptions ?? 0}
          icon="🏪"
        />
        <StatCard
          label={<Trans message="Total Subscribers" />}
          value={stats?.total_subscribers ?? 0}
          icon="👥"
        />
        <StatCard
          label={<Trans message="New Subscriptions (30d)" />}
          value={stats?.new_subscriptions_30d ?? 0}
          icon="🆕"
        />
      </div>

      {/* Revenue by Plan & Top Users */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-24 mb-32">
        <RevenueByPlanSection data={stats?.revenue_by_plan ?? []} />
        <TopPayingUsersSection data={stats?.top_paying_users ?? []} />
      </div>

      {/* Subscriptions by Gateway */}
      <SubscriptionsByGatewaySection data={stats?.subscriptions_by_gateway ?? []} />
    </div>
  );
}

interface StatCardProps {
  label: React.ReactNode;
  value: React.ReactNode;
  icon: string;
}

function StatCard({label, value, icon}: StatCardProps) {
  return (
    <div className="rounded-panel border p-20 bg-paper">
      <div className="flex items-start justify-between mb-12">
        <span className="text-3xl">{icon}</span>
      </div>
      <div className="text-xs text-muted uppercase font-medium mb-8">{label}</div>
      <div className="text-3xl font-bold">{value}</div>
    </div>
  );
}

function RevenueByPlanSection({data}: {data: any[]}) {
  const totalRevenue = data.reduce((sum, plan) => sum + Number(plan.revenue), 0);

  return (
    <div className="rounded-panel border p-20">
      <h3 className="text-base font-semibold mb-16">
        <Trans message="Revenue by Plan" />
      </h3>
      <div className="space-y-16">
        {data.map(plan => {
          const percentage = totalRevenue > 0 ? (Number(plan.revenue) / totalRevenue) * 100 : 0;
          return (
            <div key={plan.id}>
              <div className="flex justify-between mb-8 text-sm">
                <div className="flex items-center gap-8">
                  <span className="font-medium">{plan.name}</span>
                  <span className="text-xs text-muted">
                    (<FormattedCurrency value={Number(plan.price) / 100} currency="USD" />/mo)
                  </span>
                </div>
                <span className="text-muted">{plan.subscriber_count} subscribers</span>
              </div>
              <div className="flex items-center gap-12">
                <div className="flex-1 h-8 bg-alt rounded-full overflow-hidden">
                  <div
                    className="h-full bg-primary"
                    style={{width: `${percentage}%`}}
                  />
                </div>
                <span className="text-sm font-medium min-w-80 text-right">
                  <FormattedCurrency value={Number(plan.revenue) / 100} currency="USD" />/mo
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function TopPayingUsersSection({data}: {data: any[]}) {
  return (
    <div className="rounded-panel border p-20">
      <h3 className="text-base font-semibold mb-16">
        <Trans message="Top Paying Users" />
      </h3>
      <div className="space-y-12">
        {data.slice(0, 6).map((user, index) => (
          <div key={user.id} className="flex items-center gap-12">
            <div className="flex items-center justify-center w-32 h-32 rounded bg-primary/10 text-primary font-bold text-sm">
              {index + 1}
            </div>
            <div className="flex-1 min-w-0">
              <div className="font-medium truncate">{user.email}</div>
              <div className="text-xs text-muted">
                {user.plan_name}
                {' - '}
                <FormattedCurrency value={Number(user.monthly_price) / 100} currency="USD" />/mo
              </div>
            </div>
            <div className="text-sm font-medium">
              <FormattedCurrency value={Number(user.yearly_value) / 100} currency="USD" />
              <span className="text-xs text-muted">/year</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function SubscriptionsByGatewaySection({data}: {data: any[]}) {
  const total = data.reduce((sum, item) => sum + item.count, 0);

  return (
    <div className="rounded-panel border p-20">
      <h3 className="text-base font-semibold mb-16">
        <Trans message="Subscriptions by Gateway" />
      </h3>
      <div className="flex gap-16">
        {data.map(item => {
          const percentage = total > 0 ? (item.count / total) * 100 : 0;
          return (
            <div key={item.gateway} className="flex-1">
              <div className="text-center mb-8">
                <div className="text-2xl font-bold">{item.count}</div>
                <div className="text-xs text-muted capitalize">{item.gateway || 'Manual'}</div>
              </div>
              <div className="h-6 bg-alt rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary"
                  style={{width: `${percentage}%`}}
                />
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

interface ArtistAccruedEarningsSectionProps {
  query: ReturnType<typeof useArtistEarnings>;
}

function ArtistAccruedEarningsSection({query}: ArtistAccruedEarningsSectionProps) {
  if (query.isPending) {
    return (
      <div className="mb-40">
        <Skeleton variant="rect" size="h-300" />
      </div>
    );
  }

  const earnings = query.data?.earnings ?? [];

  // Calculate estimated earnings (followers × $0.10 × 90%)
  const calculateEstimated = (followers: number) => {
    const gross = followers * 10; // $0.10 = 10 cents
    const fee = Math.floor(gross * 0.10);
    return gross - fee;
  };

  return (
    <div className="mb-40">
      <h2 className="mb-8 text-xl font-semibold">
        <Trans message="Artist Earnings" />
      </h2>
      <p className="text-xs text-muted mb-16">
        <Trans message="Accrual on 5th. $0.10/follower (10% fee). Artists must request payout." />
      </p>

      <div className="rounded-panel border overflow-hidden">
        <table className="w-full text-sm">
          <thead className="border-b bg-alt">
            <tr className="text-left text-xs text-muted uppercase">
              <th className="p-12 font-medium"><Trans message="Artist" /></th>
              <th className="p-12 font-medium"><Trans message="Package" /></th>
              <th className="p-12 font-medium"><Trans message="Followers" /></th>
              <th className="p-12 font-medium"><Trans message="Estimated" /></th>
              <th className="p-12 font-medium"><Trans message="Accrued" /></th>
              <th className="p-12 font-medium"><Trans message="Bank" /></th>
              <th className="p-12 font-medium text-right"><Trans message="Action Needed" /></th>
            </tr>
          </thead>
          <tbody>
            {earnings.length === 0 ? (
              <tr>
                <td colSpan={7} className="p-24 text-center text-muted">
                  <Trans message="No artists found." />
                </td>
              </tr>
            ) : (
              earnings.map(earning => {
                const estimated = calculateEstimated(earning.followers);
                const hasBank = !!earning.bank_details;
                const hasAccrued = earning.net > 0;

                return (
                  <tr key={earning.artist_id} className="border-b last:border-b-0 hover:bg-hover">
                    <td className="p-12">
                      <div className="font-medium">{earning.artist_name}</div>
                      <div className="text-xs text-muted">{earning.user_email}</div>
                    </td>
                    <td className="p-12">
                      <span className="text-xs">{earning.product_name || '—'}</span>
                    </td>
                    <td className="p-12">{earning.followers}</td>
                    <td className="p-12">
                      <span className="text-muted">
                        <FormattedCurrency value={estimated / 100} currency="USD" />
                      </span>
                      <div className="text-xs text-muted">next 5th</div>
                    </td>
                    <td className="p-12">
                      {hasAccrued ? (
                        <span className="text-positive font-medium">
                          <FormattedCurrency value={earning.net / 100} currency="USD" />
                        </span>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="p-12">
                      {hasBank && earning.bank_details ? (
                        <BankDetailsButton earning={earning} />
                      ) : (
                        <span className="text-xs text-danger">No</span>
                      )}
                    </td>
                    <td className="p-12 text-right">
                      {hasAccrued && hasBank ? (
                        <span className="text-xs text-positive">Waiting for artist request</span>
                      ) : hasAccrued && !hasBank ? (
                        <span className="text-xs text-danger">No bank details</span>
                      ) : !hasBank ? (
                        <span className="text-xs text-muted">Add bank details</span>
                      ) : (
                        <span className="text-xs text-muted">Wait for accrual</span>
                      )}
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

interface ArtistPayoutRequestsSectionProps {
  query: ReturnType<typeof useAdminPayouts>;
  statusFilter: string;
  setStatusFilter: (status: string) => void;
  searchQuery: string;
  setSearchQuery: (query: string) => void;
}

function ArtistPayoutRequestsSection({
  query,
  statusFilter,
  setStatusFilter,
  searchQuery,
  setSearchQuery,
}: ArtistPayoutRequestsSectionProps) {
  return (
    <div>
      <h2 className="mb-8 text-xl font-semibold">
        <Trans message="Artist Payout Requests" />
      </h2>
      <p className="text-sm text-muted mb-24">
        <Trans message="Review and approve artist payout requests." />
      </p>

      {/* Filters */}
      <div className="flex items-center gap-16 mb-16">
        <TextField
          className="flex-1"
          placeholder="Search artist..."
          value={searchQuery}
          onChange={e => setSearchQuery(e.target.value)}
        />
        <Select
          selectionMode="single"
          selectedValue={statusFilter}
          onSelectionChange={(value) => setStatusFilter(String(value))}
          minWidth="min-w-180"
        >
          <Option value="all"><Trans message="All Status" /></Option>
          <Option value="pending"><Trans message="Pending" /></Option>
          <Option value="approved"><Trans message="Approved" /></Option>
          <Option value="processing"><Trans message="Processing" /></Option>
          <Option value="completed"><Trans message="Completed" /></Option>
          <Option value="rejected"><Trans message="Rejected" /></Option>
          <Option value="failed"><Trans message="Failed" /></Option>
        </Select>
      </div>

      {/* Payouts Table */}
      {query.isPending ? (
        <Skeleton variant="rect" size="h-400" />
      ) : (
        <div className="rounded-panel border overflow-hidden">
          <table className="w-full">
            <thead className="border-b bg-alt">
              <tr className="text-left text-sm text-muted uppercase">
                <th className="p-16 font-medium"><Trans message="Artist" /></th>
                <th className="p-16 font-medium"><Trans message="User" /></th>
                <th className="p-16 font-medium"><Trans message="Followers" /></th>
                <th className="p-16 font-medium">
                  <Tooltip
                    label={<Trans message="Totals for the payout period(s): gross before fee, fee deducted, net after fee." />}
                  >
                    <span className="cursor-help border-b border-dotted border-muted/60">
                      <Trans message="Period" />
                    </span>
                  </Tooltip>
                </th>
                <th className="p-16 font-medium">
                  <Tooltip
                    label={<Trans message="All accrued totals for the artist: gross before fee, fee deducted, net after fee." />}
                  >
                    <span className="cursor-help border-b border-dotted border-muted/60">
                      <Trans message="Total" />
                    </span>
                  </Tooltip>
                </th>
                <th className="p-16 font-medium">
                  <Tooltip
                    label={<Trans message="Net amount that will be paid to the artist." />}
                  >
                    <span className="cursor-help border-b border-dotted border-muted/60">
                      <Trans message="Net (Paid)" />
                    </span>
                  </Tooltip>
                </th>
                <th className="p-16 font-medium"><Trans message="Period" /></th>
                <th className="p-16 font-medium"><Trans message="Status" /></th>
                <th className="p-16 font-medium"><Trans message="Requested" /></th>
                <th className="p-16 font-medium text-right"><Trans message="Action" /></th>
              </tr>
            </thead>
            <tbody>
              {!query.data?.payouts?.data || query.data.payouts.data.length === 0 ? (
                <tr>
                  <td colSpan={10} className="p-40 text-center text-muted">
                    <Trans message="No payout requests yet." />
                  </td>
                </tr>
              ) : (
                query.data.payouts.data.map(payout => (
                  <tr key={payout.id} className="border-b last:border-b-0 hover:bg-hover transition-colors">
                    <td className="p-16">
                      <div className="font-medium">{payout.artist?.name || 'N/A'}</div>
                    </td>
                    <td className="p-16 text-muted">
                      {payout.artist?.user_email || '—'}
                    </td>
                    <td className="p-16">
                      {payout.followers_count ?? 0}
                    </td>
                    <td className="p-16">
                      <PriceBreakdown
                        gross={payout.period_gross}
                        fee={payout.period_fee}
                        net={payout.period_earnings}
                      />
                    </td>
                    <td className="p-16">
                      <PriceBreakdown
                        gross={payout.total_accrued_gross}
                        fee={payout.total_accrued_fee}
                        net={payout.total_accrued_earnings}
                      />
                    </td>
                    <td className="p-16">
                      <span className="text-positive font-medium">
                        <FormattedCurrency value={payout.amount / 100} currency="USD" />
                      </span>
                    </td>
                    <td className="p-16 text-xs text-muted">
                      {payout.period || '—'}
                    </td>
                    <td className="p-16">
                      <PayoutStatusChip status={payout.status} />
                    </td>
                    <td className="p-16">
                      <FormattedDate date={payout.requested_at} />
                    </td>
                    <td className="p-16 text-right">
                      <PayoutActions payout={payout} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

interface PayoutStatusChipProps {
  status: string;
}

function PayoutStatusChip({status}: PayoutStatusChipProps) {
  const getColor = () => {
    switch (status) {
      case 'completed':
        return 'positive';
      case 'pending':
        return 'warning';
      case 'approved':
      case 'processing':
        return 'primary';
      case 'rejected':
      case 'failed':
        return 'danger';
      default:
        return 'chip';
    }
  };

  const getLabel = () => {
    return status.charAt(0).toUpperCase() + status.slice(1);
  };

  return (
    <Chip color={getColor()} size="sm">
      {getLabel()}
    </Chip>
  );
}

interface PayoutActionsProps {
  payout: ArtistPayout;
}

function PayoutActions({payout}: PayoutActionsProps) {
  const approvePayout = useApprovePayout();
  const rejectPayout = useRejectPayout();
  const markAsCompleted = useMarkPayoutAsCompleted();

  if (payout.status === 'pending') {
    return (
      <div className="flex gap-8 justify-end">
        <Button
          variant="flat"
          color="primary"
          size="xs"
          onClick={() => approvePayout.mutate({id: payout.id})}
          disabled={approvePayout.isPending}
        >
          <Trans message="Approve" />
        </Button>
        <Button
          variant="outline"
          color="danger"
          size="xs"
          onClick={() => rejectPayout.mutate({id: payout.id, rejection_reason: 'Rejected by admin'})}
          disabled={rejectPayout.isPending}
        >
          <Trans message="Reject" />
        </Button>
      </div>
    );
  }

  if (payout.status === 'approved' || payout.status === 'processing') {
    return (
      <div className="flex gap-8 justify-end">
        <Button
          variant="flat"
          color="positive"
          size="xs"
          onClick={() => markAsCompleted.mutate(payout.id)}
          disabled={markAsCompleted.isPending}
        >
          <Trans message="Complete" />
        </Button>
      </div>
    );
  }

  return <span className="text-muted">—</span>;
}

interface BankDetailsButtonProps {
  earning: ArtistEarning;
}

interface PriceBreakdownProps {
  gross?: number;
  fee?: number;
  net?: number;
}

function PriceBreakdown({gross, fee, net}: PriceBreakdownProps) {
  const hasData = gross !== undefined || fee !== undefined || net !== undefined;
  if (!hasData) return <span>—</span>;

  return (
    <div className="space-y-4 text-xs">
      <div>
        <span className="text-muted"><Trans message="Gross:" /></span>{' '}
        <FormattedCurrency value={(gross || 0) / 100} currency="USD" />
      </div>
      <div>
        <span className="text-muted"><Trans message="Fee:" /></span>{' '}
        <FormattedCurrency value={-((fee || 0) / 100)} currency="USD" />
      </div>
      <div>
        <span className="text-muted"><Trans message="Net:" /></span>{' '}
        <FormattedCurrency value={(net || 0) / 100} currency="USD" />
      </div>
    </div>
  );
}

function BankDetailsButton({earning}: BankDetailsButtonProps) {
  const bank = earning.bank_details;
  if (!bank) return null;

  return (
    <DialogTrigger type="modal">
      <Button variant="text" color="primary" size="xs">
        <Trans message="View" />
      </Button>
      <BankDetailsDialog earning={earning} />
    </DialogTrigger>
  );
}

interface BankDetailsDialogProps {
  earning: ArtistEarning;
}

function BankDetailsDialog({earning}: BankDetailsDialogProps) {
  const bank = earning.bank_details;
  if (!bank) return null;

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
  };

  return (
    <Dialog size="sm">
      <DialogHeader>
        <Trans message="Bank Details - :name" values={{name: earning.artist_name}} />
      </DialogHeader>
      <DialogBody>
        <div className="space-y-12">
          {bank.type === 'paypal' ? (
            <DetailRow label="PayPal Email" value={bank.paypal_email} onCopy={copyToClipboard} />
          ) : (
            <>
              <DetailRow label="Account Holder" value={bank.account_holder_name} onCopy={copyToClipboard} />
              <DetailRow label="Bank Name" value={bank.bank_name} onCopy={copyToClipboard} />
              {bank.iban && <DetailRow label="IBAN" value={bank.iban} onCopy={copyToClipboard} />}
              {bank.account_number && <DetailRow label="Account Number" value={bank.account_number} onCopy={copyToClipboard} />}
              {bank.routing_number && <DetailRow label="Routing Number" value={bank.routing_number} onCopy={copyToClipboard} />}
              {bank.swift_code && <DetailRow label="SWIFT/BIC" value={bank.swift_code} onCopy={copyToClipboard} />}
              {bank.country && <DetailRow label="Country" value={bank.country} onCopy={copyToClipboard} />}
            </>
          )}
        </div>
        <div className="mt-16 pt-16 border-t text-xs text-muted">
          <Trans message="User: :email" values={{email: earning.user_email || '—'}} />
        </div>
      </DialogBody>
    </Dialog>
  );
}

interface DetailRowProps {
  label: string;
  value?: string | null;
  onCopy: (text: string) => void;
}

function DetailRow({label, value, onCopy}: DetailRowProps) {
  if (!value) return null;

  return (
    <div className="flex items-center justify-between gap-8">
      <div>
        <div className="text-xs text-muted">{label}</div>
        <div className="font-medium">{value}</div>
      </div>
      <Button
        variant="text"
        size="xs"
        onClick={() => onCopy(value)}
      >
        <Trans message="Copy" />
      </Button>
    </div>
  );
}
