import {User} from '@ui/types/user';
import {FileEntry} from '@common/uploads/file-entry';

export type {FileEntry};

export interface ChatConversation {
  id: number;
  user_one_id: number;
  user_two_id: number;
  user_one?: User;
  user_two?: User;
  last_message_id?: number | null;
  last_message_at?: string | null;
  last_message_body?: string | null;
  last_message_user_id?: number | null;
  last_message_attachments_count?: number;
  user_one_read_at?: string | null;
  user_one_last_read_message_id?: number | null;
  user_one_unread_count?: number;
  user_one_archived_at?: string | null;
  user_one_muted_at?: string | null;
  user_two_read_at?: string | null;
  user_two_last_read_message_id?: number | null;
  user_two_unread_count?: number;
  user_two_archived_at?: string | null;
  user_two_muted_at?: string | null;
  blocked_at?: string | null;
  blocked_by?: number | null;
  blocked_user_id?: number | null;
  blocked_reason?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ChatMessage {
  id: number;
  conversation_id: number;
  user_id: number;
  user?: User;
  body: string;
  edited_at?: string | null;
  deleted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}
