import {apiClient, queryClient} from '@common/http/query-client';
import {useMutation, useQuery} from '@tanstack/react-query';
import {Button} from '@ui/buttons/button';
import {Checkbox} from '@ui/forms/toggle/checkbox';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {Trans} from '@ui/i18n/trans';
import {ProgressBar} from '@ui/progress/progress-bar';
import {toast} from '@ui/toast/toast';
import {useState} from 'react';

interface RecipientUser {
  id: number;
  name: string;
  email: string;
}

interface EmailLog {
  id: number;
  subject: string;
  body: string;
  is_html: boolean;
  sent_count: number;
  total_count: number;
  failed?: string | null;
  created_at: string;
}

export function Component() {
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [sendToAll, setSendToAll] = useState(true);
  const [isHtml, setIsHtml] = useState(false);
  const [previewHtml, setPreviewHtml] = useState<string | null>(null);
  const [selectedUserIds, setSelectedUserIds] = useState<Set<number>>(new Set());

  const {data: recipientsData, isLoading: isLoadingRecipients} = useQuery({
    queryKey: ['admin', 'artist-emails', 'recipients'],
    queryFn: () =>
      apiClient
        .get<{users: RecipientUser[]}>('admin/artist-emails/recipients')
        .then(r => r.data),
  });

  const users = recipientsData?.users ?? [];

  const {data: historyData} = useQuery({
    queryKey: ['admin', 'artist-emails', 'history'],
    queryFn: () =>
      apiClient
        .get<{logs: EmailLog[]}>('admin/artist-emails/history')
        .then(r => r.data),
  });

  const logs = historyData?.logs ?? [];

  const sendMutation = useMutation({
    mutationFn: () =>
      apiClient
        .post('admin/artist-emails/send', {
          subject,
          body,
          html: isHtml,
          all: sendToAll,
          user_ids: sendToAll ? [] : Array.from(selectedUserIds),
        })
        .then(r => r.data),
    onSuccess: (data: any) => {
      if (data?.failed?.length) {
        toast(`Sent ${data.sent}/${data.total}. Failed: ${data.failed.join(', ')}`);
      } else {
        toast(`Email sent to ${data.sent} artist(s)`);
      }
      setSubject('');
      setBody('');
      setSelectedUserIds(new Set());
      setPreviewHtml(null);
      queryClient.invalidateQueries({queryKey: ['admin', 'artist-emails', 'history']});
    },
    onError: () => {
      toast.danger('Could not send the email. Check the logs.');
    },
  });

  const previewMutation = useMutation({
    mutationFn: (payload: {body: string; html: boolean}) =>
      apiClient.post<{html: string}>('admin/artist-emails/preview', payload).then(r => r.data),
    onSuccess: data => setPreviewHtml(data.html),
    onError: () => toast.danger('Could not generate the preview.'),
  });

  const toggleUser = (userId: number) => {
    setSelectedUserIds(prev => {
      const next = new Set(prev);
      if (next.has(userId)) {
        next.delete(userId);
      } else {
        next.add(userId);
      }
      return next;
    });
  };

  const canSubmit =
    subject.trim().length > 0 &&
    body.trim().length > 0 &&
    (sendToAll || selectedUserIds.size > 0) &&
    !sendMutation.isPending;

  return (
    <div className="mx-auto max-w-[800px] p-24">
      <h1 className="mb-4 text-2xl font-semibold">
        <Trans message="Email artists" />
      </h1>
      <p className="mb-24 text-sm text-muted">
        <Trans message="Send an email to all your artists or a selection of them. The email is sent from your site address." />
      </p>

      <TextField
        className="mb-20"
        label={<Trans message="Subject" />}
        value={subject}
        onChange={e => setSubject(e.target.value)}
        placeholder="Your announcement subject"
      />

      <TextField
        className="mb-20"
        label={<Trans message="Message" />}
        inputElementType="textarea"
        rows={10}
        value={body}
        onChange={e => setBody(e.target.value)}
        placeholder="Write your message here. Line breaks are kept in the email."
      />

      <div className="mb-16">
        <Checkbox checked={isHtml} onChange={() => setIsHtml(!isHtml)}>
          <Trans message="Message is HTML code" />
        </Checkbox>
      </div>

      <div className="mb-16">
        <Checkbox checked={sendToAll} onChange={() => setSendToAll(!sendToAll)}>
          <Trans
            message="Send to all artists (:count)"
            values={{count: users.length}}
          />
        </Checkbox>
      </div>

      {!sendToAll && (
        <div className="mb-20 max-h-320 overflow-y-auto rounded-panel border p-12">
          {isLoadingRecipients && <ProgressBar isIndeterminate size="sm" />}
          {!isLoadingRecipients && users.length === 0 && (
            <div className="text-sm text-muted">
              <Trans message="No artist users found." />
            </div>
          )}
          {users.map(u => (
            <div key={u.id} className="py-4">
              <Checkbox
                checked={selectedUserIds.has(u.id)}
                onChange={() => toggleUser(u.id)}
              >
                {u.name} — {u.email}
              </Checkbox>
            </div>
          ))}
        </div>
      )}

      <Button
        variant="outline"
        className="mr-12"
        disabled={body.trim().length === 0 || previewMutation.isPending}
        onClick={() => previewMutation.mutate({body, html: isHtml})}
      >
        {previewMutation.isPending ? (
          <Trans message="Loading preview..." />
        ) : (
          <Trans message="Preview" />
        )}
      </Button>

      <Button
        variant="flat"
        color="primary"
        disabled={!canSubmit}
        onClick={() => sendMutation.mutate()}
      >
        {sendMutation.isPending ? (
          <Trans message="Sending..." />
        ) : sendToAll ? (
          <Trans message="Send to all :count artists" values={{count: users.length}} />
        ) : (
          <Trans message="Send to :count selected" values={{count: selectedUserIds.size}} />
        )}
      </Button>

      {previewHtml != null && (
        <div className="mt-24">
          <h2 className="mb-8 text-lg font-semibold">
            <Trans message="Preview" />
          </h2>
          <iframe
            title="Email preview"
            srcDoc={previewHtml}
            className="h-540 w-full rounded-panel border bg-white"
          />
        </div>
      )}

      <div className="mt-34">
        <h2 className="mb-12 text-lg font-semibold">
          <Trans message="Send history" />
        </h2>
        {logs.length === 0 && (
          <div className="text-sm text-muted">
            <Trans message="No emails sent yet." />
          </div>
        )}
        {logs.map(log => {
          const failed = log.failed ? JSON.parse(log.failed) : [];
          return (
            <div
              key={log.id}
              className="mb-8 flex items-center justify-between gap-12 rounded-panel border p-12"
            >
              <div className="min-w-0">
                <div className="truncate font-medium">{log.subject}</div>
                <div className="text-xs text-muted">
                  {new Date(log.created_at).toLocaleString()} —{' '}
                  <Trans
                    message="sent to :sent of :total"
                    values={{sent: log.sent_count, total: log.total_count}}
                  />
                  {log.is_html ? ' — HTML' : ''}
                  {failed.length > 0 && (
                    <span className="text-danger"> — failed: {failed.join(', ')}</span>
                  )}
                </div>
              </div>
              <Button
                variant="outline"
                size="xs"
                onClick={() => previewMutation.mutate({body: log.body, html: !!log.is_html})}
              >
                <Trans message="View" />
              </Button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
