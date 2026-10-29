import {useState} from 'react';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {SearchIcon} from '@ui/icons/material/Search';
import {FilterListIcon} from '@ui/icons/material/FilterList';
import {BlockIcon} from '@ui/icons/material/Block';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {ChatConversation} from '@app/web-player/chat/chat-types';
import {useMutation} from '@tanstack/react-query';
import {toast} from '@ui/toast/toast';
import {Trans} from '@ui/i18n/trans';
import {ConfirmationDialog} from '@ui/overlays/dialog/confirmation-dialog';
import {openDialog} from '@ui/overlays/store/dialog-store';
import {Avatar} from '@ui/avatar/avatar';
import {Skeleton} from '@ui/skeleton/skeleton';
import {AdminConversationModal} from './admin-conversation-modal';
import {BlockUserDialog} from './block-user-dialog';

interface AdminConversationsResponse {
  conversations: {
    data: ChatConversation[];
    next_cursor?: string;
  };
}

export function Component() {
  return <AdminChatPage />;
}

function AdminChatPage() {
  const [filter, setFilter] = useState<'all' | 'blocked'>('all');
  const [searchUserId, setSearchUserId] = useState('');

  return (
    <div className="p-24">
      <div className="mb-24">
        <h1 className="text-2xl font-bold mb-4">
          <Trans message="Chat Management" />
        </h1>
        <p className="text-muted">
          <Trans message="View and moderate chat conversations" />
        </p>
      </div>

      <div className="mb-24 space-y-16">
        <TextField
          placeholder="Search by User ID, Name, or Email..."
          value={searchUserId}
          onChange={e => setSearchUserId(e.target.value)}
          startAdornment={<SearchIcon />}
          className="max-w-400"
        />
        <div className="flex gap-8">
          <Button
            variant={filter === 'all' ? 'flat' : 'outline'}
            color={filter === 'all' ? 'primary' : undefined}
            size="sm"
            onClick={() => setFilter('all')}
            startIcon={<FilterListIcon />}
          >
            <Trans message="All" />
          </Button>
          <Button
            variant={filter === 'blocked' ? 'flat' : 'outline'}
            color={filter === 'blocked' ? 'danger' : undefined}
            size="sm"
            onClick={() => setFilter('blocked')}
            startIcon={<BlockIcon />}
          >
            <Trans message="Blocked" />
          </Button>
        </div>
      </div>

      <AdminConversationsList filter={filter} searchUserId={searchUserId} />
    </div>
  );
}

interface AdminConversationsListProps {
  filter: 'all' | 'blocked';
  searchUserId: string;
}

function AdminConversationsList({
  filter,
  searchUserId,
}: AdminConversationsListProps) {
  const {data, isLoading} = useQuery({
    queryKey: ['admin-conversations', filter, searchUserId],
    queryFn: () =>
      apiClient
        .get<AdminConversationsResponse>('admin/conversations', {
          params: {
            blocked: filter === 'blocked' ? 'true' : undefined,
            user_search: searchUserId || undefined,
            user_id: searchUserId || undefined,
          },
        })
        .then(r => r.data),
  });

  if (isLoading) {
    return (
      <div className="space-y-12">
        {[...Array(5)].map((_, i) => (
          <Skeleton key={i} className="h-80" />
        ))}
      </div>
    );
  }

  const conversations = data?.conversations.data || [];

  if (conversations.length === 0) {
    return (
      <div className="flex items-center justify-center p-48 text-center">
        <div className="text-muted">
          <Trans message="No conversations found" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-12">
      {conversations.map(conversation => (
        <AdminConversationItem
          key={conversation.id}
          conversation={conversation}
        />
      ))}
    </div>
  );
}

interface AdminConversationItemProps {
  conversation: ChatConversation;
}

function AdminConversationItem({conversation}: AdminConversationItemProps) {
  const queryClient = useQueryClient();

  const blockMutation = useMutation({
    mutationFn: (payload: {block: boolean; user_id?: number | null; reason?: string | null}) =>
      apiClient.patch(`admin/conversations/${conversation.id}/block`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ['admin-conversations'],
      });
      toast.positive('Block status updated');
    },
    onError: () => {
      toast.danger('Failed to update conversation status');
    },
  });

  const handleBlock = () => {
    if (conversation.blocked_at) {
      openDialog(ConfirmationDialog, {
        isDanger: false,
        title: <Trans message="Unblock conversation" />,
        body: <Trans message="Are you sure you want to unblock this conversation? All users will be able to send messages again." />,
        confirm: <Trans message="Unblock" />,
        onConfirm: () => {
          blockMutation.mutate({
            block: false,
            reason: null,
          });
        },
      });
    } else {
      openDialog(BlockUserDialog, {conversation});
    }
  };

  const handleViewConversation = () => {
    openDialog(AdminConversationModal, {conversation});
  };

  const userOne = conversation.userOne || (conversation as any).user_one;
  const userTwo = conversation.userTwo || (conversation as any).user_two;
  const displayName = (u: {name?: string; username?: string; email?: string} | undefined, id: number) =>
    u?.name || u?.username || (u?.email ? u.email.split('@')[0] : null) || `User ${id}`;
  const userOneDisplay = `${displayName(userOne, conversation.user_one_id)} (ID: ${conversation.user_one_id})`;
  const userTwoDisplay = `${displayName(userTwo, conversation.user_two_id)} (ID: ${conversation.user_two_id})`;

  return (
    <div className="rounded-panel border p-16 hover:bg-hover transition-colors">
      <div className="flex items-start gap-16">
        <div
          className="flex items-start gap-12 flex-1 min-w-0 cursor-pointer"
          onClick={handleViewConversation}
        >
          <div className="flex items-center gap-8">
            <div className="flex flex-col items-center gap-2">
              <Avatar
                src={userOne?.image}
                label={displayName(userOne, conversation.user_one_id)}
                size="md"
                circle
              />
              <div className="text-xs text-center max-w-[100px]">
                <div className="font-medium truncate">
                  {displayName(userOne, conversation.user_one_id)}
                </div>
                <div className="text-muted">ID: {conversation.user_one_id}</div>
              </div>
            </div>
            <div className="text-muted text-lg">↔</div>
            <div className="flex flex-col items-center gap-2">
              <Avatar
                src={userTwo?.image}
                label={displayName(userTwo, conversation.user_two_id)}
                size="md"
                circle
              />
              <div className="text-xs text-center max-w-[100px]">
                <div className="font-medium truncate">
                  {displayName(userTwo, conversation.user_two_id)}
                </div>
                <div className="text-muted">ID: {conversation.user_two_id}</div>
              </div>
            </div>
          </div>
          <div className="flex-1 min-w-0 ml-8">
            <div className="flex items-center gap-8 mb-2 flex-wrap">
              {conversation.blocked_at && (
                <span className="inline-flex items-center gap-4 px-8 py-4 bg-danger/20 text-danger rounded text-xs font-medium">
                  <BlockIcon className="w-12 h-12" />
                  {conversation.blocked_user_id
                    ? `Blocked: ${conversation.blocked_user_id === conversation.user_one_id ? userOneDisplay : userTwoDisplay}`
                    : 'Blocked (Both users)'}
                </span>
              )}
            </div>
            <div className="text-xs text-muted mb-2">
              Conversation #{conversation.id}
            </div>
            <div className="text-xs text-muted space-y-2">
              <div>
                Last message:{' '}
                {conversation.last_message_at
                  ? new Date(conversation.last_message_at).toLocaleString()
                  : 'Never'}
              </div>
              {conversation.last_message_body && (
                <div className="truncate max-w-600">
                  {conversation.last_message_body}
                </div>
              )}
              {conversation.blocked_at && conversation.blocked_reason && (
                <div className="text-danger mt-4">
                  Block reason: {conversation.blocked_reason}
                </div>
              )}
            </div>
          </div>
        </div>
        <IconButton
          size="sm"
          onClick={handleBlock}
          disabled={blockMutation.isPending}
          color={conversation.blocked_at ? 'primary' : 'danger'}
          title={
            conversation.blocked_at
              ? 'Unblock conversation (both users can message again)'
              : 'Block conversation (both users cannot message)'
          }
        >
          {conversation.blocked_at ? <CheckCircleIcon /> : <BlockIcon />}
        </IconButton>
      </div>
    </div>
  );
}
