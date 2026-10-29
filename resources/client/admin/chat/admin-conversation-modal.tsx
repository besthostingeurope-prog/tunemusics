import { ChatConversation, FileEntry } from '@app/web-player/chat/chat-types';
import { Avatar } from '@ui/avatar/avatar';
import { FormattedDate } from '@ui/i18n/formatted-date';
import { Trans } from '@ui/i18n/trans';
import { Dialog } from '@ui/overlays/dialog/dialog';
import { DialogBody } from '@ui/overlays/dialog/dialog-body';
import { useDialogContext } from '@ui/overlays/dialog/dialog-context';
import { DialogHeader } from '@ui/overlays/dialog/dialog-header';
import { Skeleton } from '@ui/skeleton/skeleton';
import { useFetchAdminConversation } from './requests/use-fetch-admin-conversation';

interface AdminConversationModalProps {
  conversation: ChatConversation;
}

export function AdminConversationModal({
  conversation,
}: AdminConversationModalProps) {
  const {close} = useDialogContext();
  const {data, isLoading, error} = useFetchAdminConversation(conversation.id);

  const messages =
    data?.messages?.data ||
    (Array.isArray(data?.messages) ? data.messages : []) ||
    [];
  const conversationData = data?.conversation || conversation;

  const userOne = conversationData.userOne || (conversationData as any).user_one;
  const userTwo = conversationData.userTwo || (conversationData as any).user_two;
  const displayName = (u: {name?: string; username?: string; email?: string} | undefined, id: number) =>
    u?.name || u?.username || (u?.email ? u.email.split('@')[0] : null) || `User ${id}`;

  const getUserName = (userId: number) => {
    if (userOne?.id === userId) return displayName(userOne, userOne.id);
    if (userTwo?.id === userId) return displayName(userTwo, userTwo.id);
    return `User ${userId}`;
  };

  const getUserInfo = (userId: number) => {
    if (userOne?.id === userId) return userOne;
    if (userTwo?.id === userId) return userTwo;
    return null;
  };

  const isFromFirstUser = (userId: number) => {
    const userOneId = userOne?.id ?? conversation.user_one_id;
    return userId === userOneId;
  };

  const sortedMessages = [...messages].reverse();

  return (
    <Dialog size="lg" maxWidth="max-w-3xl">
      <DialogHeader>
        <Trans
          message="Conversation: :user1 ↔ :user2"
          values={{
            user1: displayName(userOne, conversation.user_one_id),
            user2: displayName(userTwo, conversation.user_two_id),
          }}
        />
      </DialogHeader>
      <DialogBody>
        {error && (
          <div className="text-center text-danger py-8">
            <Trans message="Error loading messages. Please try again." />
            <div className="text-xs mt-2">{String(error)}</div>
          </div>
        )}
        {isLoading ? (
          <div className="space-y-4">
            {[...Array(5)].map((_, i) => (
              <Skeleton key={i} className="h-16" />
            ))}
          </div>
        ) : sortedMessages.length === 0 ? (
          <div className="text-center text-muted py-8">
            <Trans message="No messages in this conversation" />
          </div>
        ) : (
          <div className="space-y-4 max-h-[60vh] overflow-y-auto px-2 py-4">
            {sortedMessages.map(message => {
              const isFromFirst = isFromFirstUser(message.user_id);
              const userInfo = getUserInfo(message.user_id);
              const displayName =
                message.user?.name ||
                userInfo?.name ||
                getUserName(message.user_id);

              return (
                <div
                  key={message.id}
                  className={`flex ${isFromFirst ? 'justify-start' : 'justify-end'} mb-4`}
                >
                  <div
                    className={`max-w-[75%] ${isFromFirst ? 'items-start' : 'items-end'} flex flex-col`}
                  >
                    {isFromFirst && (
                      <div className="flex items-center gap-2 mb-1">
                        <Avatar
                          src={userInfo?.image || message.user?.image}
                          label={displayName}
                          size="xs"
                          circle
                          fallback="initials"
                        />
                        <span className="text-xs font-medium text-main">
                          {displayName}
                        </span>
                      </div>
                    )}
                    <div
                      className={`
                        px-4 py-3 rounded-2xl break-words
                        ${
                          isFromFirst
                            ? 'bg-chip text-main rounded-tl-sm'
                            : 'bg-primary text-white rounded-tr-sm'
                        }
                      `}
                    >
                      {message.body && (
                        <div className="whitespace-pre-wrap leading-relaxed">
                          {message.body}
                        </div>
                      )}
                      {message.attachments && message.attachments.length > 0 && (
                        <div className={`mt-2 space-y-2 ${message.body ? '' : ''}`}>
                          {message.attachments.map((attachment: FileEntry) => {
                            const isImage = attachment.mime?.startsWith('image/') ||
                              /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(attachment.name || '');
                            const downloadUrl = attachment.hash
                              ? `/file-entries/download/${attachment.hash}`
                              : attachment.url;

                            if (isImage) {
                              return (
                                <div key={attachment.id} className="rounded-lg overflow-hidden max-w-full">
                                  <img
                                    src={attachment.url}
                                    alt={attachment.name || 'Image attachment'}
                                    className="max-w-full h-auto rounded-lg cursor-pointer hover:opacity-90 transition-opacity"
                                    onClick={() => window.open(attachment.url, '_blank')}
                                    loading="lazy"
                                  />
                                </div>
                              );
                            }

                            return (
                              <a
                                key={attachment.id}
                                href={downloadUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`text-sm underline ${isFromFirst ? 'text-primary' : 'text-white'}`}
                              >
                                📎 {attachment.name}
                              </a>
                            );
                          })}
                        </div>
                      )}
                    </div>
                    {!isFromFirst && (
                      <div className="flex items-center gap-2 mt-1 justify-end">
                        <span className="text-xs font-medium text-main">
                          {displayName}
                        </span>
                        <Avatar
                          src={userInfo?.image || message.user?.image}
                          label={displayName}
                          size="xs"
                          circle
                          fallback="initials"
                        />
                      </div>
                    )}
                    <div
                      className={`text-xs text-muted mt-1 ${isFromFirst ? 'text-left' : 'text-right'}`}
                    >
                      <FormattedDate date={message.created_at} preset="time" />
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </DialogBody>
    </Dialog>
  );
}
