import { createEcho, disconnectEcho, ReverbConfig } from '@/lib/echo';
import { SharedData } from '@/types/global';
import Echo from 'laravel-echo';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

type ConversationListItem = {
   id: number;
   type: 'direct' | 'group' | 'academy';
   course_id: number | null;
   course_title?: string | null;
   label: string;
   last_message_at?: string | null;
   preview?: string | null;
   preview_sender?: string | null;
   unread?: boolean;
   is_resolved?: boolean;
   is_muted?: boolean;
};

type ChatMessageItem = {
   id: number;
   body?: string | null;
   attachment?: string | null;
   attachment_name?: string | null;
   attachment_type?: 'image' | 'video' | 'pdf' | null;
   created_at?: string | null;
   is_mine?: boolean;
   can_delete?: boolean;
   is_pinned?: boolean;
   sender?: { id?: number; name?: string; photo?: string | null; role?: string };
};

type InboxUpdatedPayload = {
   inbox_preview: ConversationListItem;
   messages_unread_count: number;
};

type MessageSentPayload = {
   conversation_id: number;
   message: ChatMessageItem;
   sender_id: number;
};

type MessagesRealtimeContextValue = {
   messagesUnreadCount: number;
   setMessagesUnreadCount: (count: number) => void;
   activeConversationId: number | null;
   setActiveConversationId: (id: number | null) => void;
   inboxPreviews: Record<number, ConversationListItem>;
   mergeInboxPreview: (preview: ConversationListItem) => void;
   onConversationMessage: (conversationId: number, handler: (message: ChatMessageItem) => void) => () => void;
   echoConnected: boolean;
};

const MessagesRealtimeContext = createContext<MessagesRealtimeContextValue | null>(null);

const POLL_INTERVAL_MS = 45_000;
const PRESENCE_INTERVAL_MS = 30_000;

async function postPresence(conversationId: number | null, visible: boolean) {
   await fetch(route('messages.presence'), {
      method: 'POST',
      headers: {
         Accept: 'application/json',
         'Content-Type': 'application/json',
         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
         conversation_id: conversationId,
         visible,
      }),
   });
}

async function fetchUnreadCount(): Promise<number> {
   const response = await fetch(route('messages.unread'), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
   });

   if (!response.ok) {
      return 0;
   }

   const data = (await response.json()) as { messages_unread_count?: number };

   return data.messages_unread_count ?? 0;
}

export function MessagesRealtimeProvider({
   reverb,
   userId,
   initialUnreadCount,
   children,
}: {
   reverb?: ReverbConfig;
   userId?: number | null;
   initialUnreadCount?: number;
   children: ReactNode;
}) {
   const [messagesUnreadCount, setMessagesUnreadCount] = useState(initialUnreadCount ?? 0);
   const [activeConversationId, setActiveConversationId] = useState<number | null>(null);
   const [inboxPreviews, setInboxPreviews] = useState<Record<number, ConversationListItem>>({});
   const [echoConnected, setEchoConnected] = useState(false);
   const echoRef = useRef<Echo | null>(null);
   const conversationHandlersRef = useRef<Map<number, Set<(message: ChatMessageItem) => void>>>(new Map());
   const tabVisibleRef = useRef(typeof document !== 'undefined' ? !document.hidden : true);

   const mergeInboxPreview = useCallback((preview: ConversationListItem) => {
      setInboxPreviews((current) => ({ ...current, [preview.id]: preview }));
   }, []);

   const onConversationMessage = useCallback((conversationId: number, handler: (message: ChatMessageItem) => void) => {
      if (!conversationHandlersRef.current.has(conversationId)) {
         conversationHandlersRef.current.set(conversationId, new Set());
      }

      conversationHandlersRef.current.get(conversationId)?.add(handler);

      return () => {
         conversationHandlersRef.current.get(conversationId)?.delete(handler);
      };
   }, []);

   useEffect(() => {
      setMessagesUnreadCount(initialUnreadCount ?? 0);
   }, [initialUnreadCount]);

   useEffect(() => {
      if (!userId || !reverb?.enabled) {
         return;
      }

      const echo = createEcho(reverb);
      echoRef.current = echo;

      if (!echo) {
         return;
      }

      const userChannel = echo.private(`App.Models.User.${userId}`);

      userChannel.listen('.chat.inbox.updated', (payload: InboxUpdatedPayload) => {
         mergeInboxPreview(payload.inbox_preview);
         setMessagesUnreadCount(payload.messages_unread_count);
      });

      userChannel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', () => {
         window.dispatchEvent(new CustomEvent('ssu:notifications-updated'));
      });

      const connector = echo.connector as { pusher?: { connection: { bind: (event: string, cb: () => void) => void } } };
      connector.pusher?.connection.bind('connected', () => setEchoConnected(true));
      connector.pusher?.connection.bind('disconnected', () => setEchoConnected(false));
      connector.pusher?.connection.bind('unavailable', () => setEchoConnected(false));

      return () => {
         userChannel.stopListening('.chat.inbox.updated');
         userChannel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
         disconnectEcho();
         echoRef.current = null;
         setEchoConnected(false);
      };
   }, [mergeInboxPreview, reverb, userId]);

   useEffect(() => {
      if (!userId || !reverb?.enabled || !echoRef.current) {
         return;
      }

      const echo = echoRef.current;
      let channel: ReturnType<Echo['private']> | null = null;

      if (activeConversationId) {
         channel = echo.private(`chat.conversation.${activeConversationId}`);

         channel.listen('.message.sent', (payload: MessageSentPayload) => {
            const handlers = conversationHandlersRef.current.get(payload.conversation_id);
            handlers?.forEach((handler) => handler(payload.message));
         });
      }

      return () => {
         if (channel && activeConversationId) {
            channel.stopListening('.message.sent');
            echo.leave(`chat.conversation.${activeConversationId}`);
         }
      };
   }, [activeConversationId, reverb?.enabled, userId]);

   useEffect(() => {
      if (!userId) {
         return;
      }

      const sendPresence = () => {
         void postPresence(activeConversationId, tabVisibleRef.current);
      };

      const onVisibility = () => {
         tabVisibleRef.current = !document.hidden;
         sendPresence();
      };

      sendPresence();
      const presenceTimer = window.setInterval(sendPresence, PRESENCE_INTERVAL_MS);
      document.addEventListener('visibilitychange', onVisibility);

      return () => {
         window.clearInterval(presenceTimer);
         document.removeEventListener('visibilitychange', onVisibility);
         void postPresence(null, false);
      };
   }, [activeConversationId, userId]);

   useEffect(() => {
      if (!userId || echoConnected) {
         return;
      }

      const poll = () => {
         void fetchUnreadCount().then(setMessagesUnreadCount);
      };

      poll();
      const pollTimer = window.setInterval(poll, POLL_INTERVAL_MS);

      return () => window.clearInterval(pollTimer);
   }, [echoConnected, userId]);

   const value = useMemo(
      () => ({
         messagesUnreadCount,
         setMessagesUnreadCount,
         activeConversationId,
         setActiveConversationId,
         inboxPreviews,
         mergeInboxPreview,
         onConversationMessage,
         echoConnected,
      }),
      [
         activeConversationId,
         echoConnected,
         inboxPreviews,
         mergeInboxPreview,
         messagesUnreadCount,
         onConversationMessage,
      ],
   );

   return <MessagesRealtimeContext.Provider value={value}>{children}</MessagesRealtimeContext.Provider>;
}

export function useMessagesRealtime() {
   return useContext(MessagesRealtimeContext);
}

export function useMessagesUnreadCount(fallback = 0) {
   const ctx = useMessagesRealtime();

   return ctx?.messagesUnreadCount ?? fallback;
}

export type { ChatMessageItem, ConversationListItem };
