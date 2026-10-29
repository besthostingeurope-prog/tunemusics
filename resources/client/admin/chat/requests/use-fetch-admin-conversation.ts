import {useQuery} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {ChatConversation, ChatMessage} from '@app/web-player/chat/chat-types';

interface FetchAdminConversationResponse {
  conversation: ChatConversation;
  messages: {
    data: ChatMessage[];
    next_cursor?: string;
  };
}

export function useFetchAdminConversation(conversationId: number | null) {
  return useQuery({
    queryKey: ['admin', 'conversation', conversationId],
    queryFn: async () => {
      const response = await apiClient.get<FetchAdminConversationResponse>(
        `admin/conversations/${conversationId}`,
      );
      return response.data;
    },
    enabled: !!conversationId,
  });
}
