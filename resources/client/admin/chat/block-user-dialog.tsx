import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {ChatConversation} from '@app/web-player/chat/chat-types';
import {Button} from '@ui/buttons/button';
import {Trans} from '@ui/i18n/trans';
import {Avatar} from '@ui/avatar/avatar';
import {useState} from 'react';
import {apiClient} from '@common/http/query-client';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {toast} from '@ui/toast/toast';
import {BlockIcon} from '@ui/icons/material/Block';

interface BlockUserDialogProps {
  conversation: ChatConversation;
}

export function BlockUserDialog({conversation}: BlockUserDialogProps) {
  const {close} = useDialogContext();
  const queryClient = useQueryClient();
  const [selectedOption, setSelectedOption] = useState<'both' | 'user_one' | 'user_two' | null>(null);
  const [reason, setReason] = useState('');

  const userOne = conversation.userOne || (conversation as any).user_one;
  const userTwo = conversation.userTwo || (conversation as any).user_two;
  const displayName = (u: {name?: string; username?: string; email?: string} | undefined, id: number) =>
    u?.name || u?.username || (u?.email ? u.email.split('@')[0] : null) || `User ${id}`;

  const blockMutation = useMutation({
    mutationFn: (payload: {block: boolean; user_id?: number | null; reason?: string | null}) =>
      apiClient.patch(`admin/conversations/${conversation.id}/block`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ['admin-conversations'],
      });
      toast.positive('User blocked successfully');
      close();
    },
    onError: () => {
      toast.danger('Failed to block user');
    },
  });

  const handleConfirm = () => {
    if (!selectedOption) {
      toast.danger('Please select who to block');
      return;
    }

    let userId: number | null = null;
    if (selectedOption === 'user_one') {
      userId = conversation.user_one_id;
    } else if (selectedOption === 'user_two') {
      userId = conversation.user_two_id;
    }

    blockMutation.mutate({
      block: true,
      user_id: userId,
      reason: reason.trim() || null,
    });
  };

  return (
    <Dialog>
      <DialogHeader>
        <Trans message="Block User" />
      </DialogHeader>
      <DialogBody>
        <div className="space-y-4">
          <div className="text-sm text-muted mb-4">
            <Trans message="Select who to block from this conversation:" />
          </div>

          <div className="space-y-2">
            <button
              onClick={() => setSelectedOption('user_one')}
              className={`w-full p-4 rounded-lg border-2 transition-colors text-left ${
                selectedOption === 'user_one'
                  ? 'border-primary bg-primary/10'
                  : 'border-divider hover:bg-hover'
              }`}
            >
              <div className="flex items-center gap-3">
                {userOne && (
                  <Avatar
                    src={userOne.image}
                    label={displayName(userOne, conversation.user_one_id)}
                    size="md"
                    circle
                  />
                )}
                <div>
                  <div className="font-medium">{displayName(userOne, conversation.user_one_id)}</div>
                  <div className="text-xs text-muted">ID: {conversation.user_one_id}</div>
                </div>
              </div>
            </button>

            <button
              onClick={() => setSelectedOption('user_two')}
              className={`w-full p-4 rounded-lg border-2 transition-colors text-left ${
                selectedOption === 'user_two'
                  ? 'border-primary bg-primary/10'
                  : 'border-divider hover:bg-hover'
              }`}
            >
              <div className="flex items-center gap-3">
                {userTwo && (
                  <Avatar
                    src={userTwo.image}
                    label={displayName(userTwo, conversation.user_two_id)}
                    size="md"
                    circle
                  />
                )}
                <div>
                  <div className="font-medium">{displayName(userTwo, conversation.user_two_id)}</div>
                  <div className="text-xs text-muted">ID: {conversation.user_two_id}</div>
                </div>
              </div>
            </button>

            <button
              onClick={() => setSelectedOption('both')}
              className={`w-full p-4 rounded-lg border-2 transition-colors text-left ${
                selectedOption === 'both'
                  ? 'border-danger bg-danger/10'
                  : 'border-divider hover:bg-hover'
              }`}
            >
              <div className="flex items-center gap-3">
                <BlockIcon className="w-20 h-20 text-danger" />
                <div>
                  <div className="font-medium text-danger">
                    <Trans message="Block Entire Conversation" />
                  </div>
                  <div className="text-xs text-muted">
                    <Trans message="Both users will be unable to send messages" />
                  </div>
                </div>
              </div>
            </button>
          </div>

          <div className="mt-4">
            <label className="block text-sm font-medium mb-2">
              <Trans message="Reason (optional)" />
            </label>
            <textarea
              value={reason}
              onChange={e => setReason(e.target.value)}
              placeholder="Enter reason for blocking..."
              className="w-full border border-divider rounded-lg px-3 py-2 min-h-[80px] resize-none focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>
      </DialogBody>
      <DialogFooter>
        <Button variant="text" onClick={() => close()}>
          <Trans message="Cancel" />
        </Button>
        <Button
          variant="flat"
          color="danger"
          onClick={handleConfirm}
          disabled={!selectedOption || blockMutation.isPending}
        >
          <Trans message="Block" />
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
