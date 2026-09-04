import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
   ChatMessageItem,
   ConversationListItem,
   useMessagesRealtime,
} from '@/contexts/messages-realtime-context';
import DashboardLayout from '@/layouts/dashboard/layout';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { BellOff, FileText, Pin, Plus, Search, Trash2, X } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

type ActiveConversation = ConversationListItem & {
   messages: ChatMessageItem[];
   can_send: boolean;
   can_moderate?: boolean;
   can_resolve?: boolean;
   can_pin?: boolean;
   is_muted?: boolean;
   pinned_message?: ChatMessageItem | null;
};

type Filters = {
   q?: string | null;
   filter?: string | null;
   mq?: string | null;
};

type Props = SharedData & {
   conversations: ConversationListItem[];
   activeConversation: ActiveConversation | null;
   canStartAcademyChat?: boolean;
   filters?: Filters;
};

const INBOX_FILTERS = [
   { value: '', label: 'All' },
   { value: 'unread', label: 'Unread' },
   { value: 'direct', label: 'Direct' },
   { value: 'academy', label: 'Academy' },
   { value: 'group', label: 'Class' },
] as const;

function formatTime(value?: string | null) {
   if (!value) return '';
   return new Date(value).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
   });
}

function inboxHref(conversationId?: number, filters?: Filters) {
   const params = new URLSearchParams();
   if (filters?.q) params.set('q', filters.q);
   if (filters?.filter) params.set('filter', filters.filter);
   if (filters?.mq) params.set('mq', filters.mq);

   const query = params.toString();

   if (conversationId) {
      const base = route('messages.show', conversationId);
      return query ? `${base}?${query}` : base;
   }

   const base = route('messages.index');
   return query ? `${base}?${query}` : base;
}

function AttachmentPreview({ message, inverted }: { message: ChatMessageItem; inverted?: boolean }) {
   if (!message.attachment) return null;

   if (message.attachment_type === 'video') {
      return (
         <video controls className="mt-2 max-h-56 w-full rounded-lg" src={message.attachment}>
            <track kind="captions" />
         </video>
      );
   }

   if (message.attachment_type === 'pdf') {
      return (
         <a
            href={message.attachment}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
               'mt-2 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs',
               inverted ? 'border-white/30 text-white' : 'border-border text-foreground',
            )}
         >
            <FileText className="h-4 w-4" />
            {message.attachment_name || 'View PDF'}
         </a>
      );
   }

   return (
      <a href={message.attachment} target="_blank" rel="noopener noreferrer" className="mt-2 block">
         <img src={message.attachment} alt={message.attachment_name || 'Attachment'} className="max-h-48 rounded-lg" />
      </a>
   );
}

function MessageBubble({
   message,
   canPin,
   onPin,
   onDelete,
}: {
   message: ChatMessageItem;
   canPin?: boolean;
   onPin?: (messageId: number) => void;
   onDelete?: (messageId: number) => void;
}) {
   return (
      <div className={cn('group flex gap-2', message.is_mine ? 'justify-end' : 'justify-start')}>
         {!message.is_mine && (
            <Avatar className="h-8 w-8">
               <AvatarImage src={message.sender?.photo || undefined} />
               <AvatarFallback>{message.sender?.name?.charAt(0) || '?'}</AvatarFallback>
            </Avatar>
         )}
         <div className="flex max-w-[75%] flex-col gap-1">
            <div
               className={cn(
                  'rounded-2xl px-3 py-2 text-sm',
                  message.is_mine ? 'bg-[#01123A] text-white' : 'bg-muted text-foreground',
               )}
            >
               {!message.is_mine && <p className="mb-1 text-[11px] font-medium opacity-70">{message.sender?.name}</p>}
               {message.is_pinned && (
                  <p className={cn('mb-1 flex items-center gap-1 text-[10px] font-medium', message.is_mine ? 'text-white/80' : 'text-muted-foreground')}>
                     <Pin className="h-3 w-3" /> Pinned
                  </p>
               )}
               {message.body && <p className="whitespace-pre-wrap">{message.body}</p>}
               <AttachmentPreview message={message} inverted={message.is_mine} />
               <p className={cn('mt-1 text-[10px]', message.is_mine ? 'text-white/70' : 'text-muted-foreground')}>
                  {formatTime(message.created_at)}
               </p>
            </div>
            {(canPin || message.can_delete) && (
               <div className={cn('flex gap-1 opacity-0 transition-opacity group-hover:opacity-100', message.is_mine ? 'justify-end' : 'justify-start')}>
                  {canPin && onPin && (
                     <Button type="button" variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => onPin(message.id)}>
                        <Pin className="mr-1 h-3 w-3" />
                        Pin
                     </Button>
                  )}
                  {message.can_delete && onDelete && (
                     <Button type="button" variant="ghost" size="sm" className="h-7 px-2 text-xs text-destructive" onClick={() => onDelete(message.id)}>
                        <Trash2 className="mr-1 h-3 w-3" />
                        Delete
                     </Button>
                  )}
               </div>
            )}
         </div>
      </div>
   );
}

type StudentOption = { id: number; name: string; email: string };

function AcademyComposer() {
   const [open, setOpen] = useState(false);
   const [query, setQuery] = useState('');
   const [students, setStudents] = useState<StudentOption[]>([]);
   const [loading, setLoading] = useState(false);
   const [startingId, setStartingId] = useState<number | null>(null);

   useEffect(() => {
      const needle = query.trim();
      if (!open || needle.length < 2) {
         setStudents([]);
         return;
      }

      const controller = new AbortController();
      const timer = window.setTimeout(() => {
         setLoading(true);
         void fetch(`${route('messages.students')}?q=${encodeURIComponent(needle)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
         })
            .then(async (response) => {
               if (!response.ok) {
                  return;
               }
               const data = (await response.json()) as { students?: StudentOption[] };
               setStudents(data.students ?? []);
            })
            .finally(() => setLoading(false));
      }, 250);

      return () => {
         controller.abort();
         window.clearTimeout(timer);
      };
   }, [open, query]);

   const startChat = (student: StudentOption) => {
      setStartingId(student.id);
      router.post(
         route('messages.academy'),
         { student_id: student.id },
         {
            preserveScroll: true,
            onFinish: () => setStartingId(null),
         },
      );
   };

   return (
      <div className="mt-3">
         {open ? (
            <div className="space-y-2">
               <div className="flex items-center justify-between gap-2">
                  <p className="text-xs font-medium">Message a student</p>
                  <Button type="button" variant="ghost" size="sm" className="h-7 px-2" onClick={() => setOpen(false)}>
                     <X className="h-4 w-4" />
                  </Button>
               </div>
               <Input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Search name or email…"
                  className="h-9"
                  autoFocus
               />
               <div className="max-h-48 overflow-y-auto rounded-md border border-border/60">
                  {query.trim().length < 2 ? (
                     <p className="px-3 py-2 text-xs text-muted-foreground">Type at least 2 characters.</p>
                  ) : loading ? (
                     <p className="px-3 py-2 text-xs text-muted-foreground">Searching…</p>
                  ) : students.length === 0 ? (
                     <p className="px-3 py-2 text-xs text-muted-foreground">No students match that search.</p>
                  ) : (
                     students.map((student) => (
                        <button
                           key={student.id}
                           type="button"
                           disabled={startingId === student.id}
                           className="block w-full border-b border-border/40 px-3 py-2 text-left last:border-b-0 hover:bg-muted/40"
                           onClick={() => startChat(student)}
                        >
                           <p className="truncate text-sm font-medium">{student.name}</p>
                           <p className="truncate text-xs text-muted-foreground">{student.email}</p>
                        </button>
                     ))
                  )}
               </div>
            </div>
         ) : (
            <Button type="button" variant="outline" size="sm" className="w-full" onClick={() => setOpen(true)}>
               <Plus className="mr-1 h-4 w-4" />
               New message
            </Button>
         )}
      </div>
   );
}

export default function MessagesIndex() {
   const pageProps = usePage<Props>().props;
   const conversations = pageProps.conversations ?? [];
   const activeConversation = pageProps.activeConversation ?? null;
   const canStartAcademyChat = Boolean(pageProps.canStartAcademyChat);
   const { auth, filters = {} } = pageProps;
   const isLearner = auth.user?.role === 'student';
   const isAdmin = auth.user?.role === 'admin';
   const inboxFilters = isAdmin ? INBOX_FILTERS : INBOX_FILTERS.filter((item) => item.value !== 'academy');
   const [inboxQuery, setInboxQuery] = useState(filters.q ?? '');
   const [threadQuery, setThreadQuery] = useState(filters.mq ?? '');
   const [liveMessages, setLiveMessages] = useState<ChatMessageItem[]>(activeConversation?.messages ?? []);
   const threadRef = useRef<HTMLDivElement>(null);
   const liveMessagesRef = useRef(liveMessages);
   const realtime = useMessagesRealtime();
   liveMessagesRef.current = liveMessages;

   useEffect(() => {
      const setActiveConversationId = realtime?.setActiveConversationId;
      setActiveConversationId?.(activeConversation?.id ?? null);

      return () => setActiveConversationId?.(null);
   }, [activeConversation?.id, realtime?.setActiveConversationId]);

   useEffect(() => {
      setLiveMessages(activeConversation?.messages ?? []);
   }, [activeConversation?.id, activeConversation?.messages]);

   useEffect(() => {
      if (!activeConversation?.id || !realtime) {
         return;
      }

      return realtime.onConversationMessage(activeConversation.id, (message) => {
         const normalized: ChatMessageItem = {
            ...message,
            is_mine: message.sender?.id === auth.user?.id,
            can_delete: message.sender?.id === auth.user?.id,
         };

         setLiveMessages((current) => {
            if (current.some((item) => item.id === normalized.id)) {
               return current;
            }

            return [...current, normalized];
         });

         void fetch(route('messages.read', activeConversation.id), {
            method: 'POST',
            headers: {
               Accept: 'application/json',
               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
         }).then(async (response) => {
            if (!response.ok) {
               return;
            }

            const data = (await response.json()) as { messages_unread_count?: number };
            if (typeof data.messages_unread_count === 'number') {
               realtime.setMessagesUnreadCount(data.messages_unread_count);
            }
         });
      });
   }, [activeConversation?.id, auth.user?.id, realtime]);

   useEffect(() => {
      if (!activeConversation?.id) {
         return;
      }

      const conversationId = activeConversation.id;

      const pollThread = () => {
         const afterId = Math.max(0, ...liveMessagesRef.current.map((message) => message.id), 0);

         void fetch(`${route('messages.sync', conversationId)}?after=${afterId}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
         }).then(async (response) => {
            if (!response.ok) {
               return;
            }

            const data = (await response.json()) as {
               messages?: ChatMessageItem[];
               inbox_preview?: ConversationListItem;
               messages_unread_count?: number;
            };

            if (data.messages?.length) {
               setLiveMessages((current) => {
                  const incoming = data.messages ?? [];
                  const next = [...current];

                  incoming.forEach((message) => {
                     if (!next.some((item) => item.id === message.id)) {
                        next.push(message);
                     }
                  });

                  return next.filter((item) => {
                     if (item.id > 0) {
                        return true;
                     }

                     return !incoming.some(
                        (message) => message.is_mine && (message.body || '') === (item.body || ''),
                     );
                  });
               });
            }

            if (data.inbox_preview) {
               realtime?.mergeInboxPreview(data.inbox_preview);
            }

            if (typeof data.messages_unread_count === 'number') {
               realtime?.setMessagesUnreadCount(data.messages_unread_count);
            }
         });
      };

      const timer = window.setInterval(pollThread, 3000);

      return () => window.clearInterval(timer);
   }, [activeConversation?.id, realtime]);

   useEffect(() => {
      const node = threadRef.current;
      if (!node) {
         return;
      }

      node.scrollTop = node.scrollHeight;
   }, [liveMessages.length, activeConversation?.id]);

   const mergedConversations = useMemo(() => {
      if (!realtime) {
         return conversations;
      }

      const merged = conversations.map((conversation) => realtime.inboxPreviews[conversation.id] ?? conversation);

      Object.values(realtime.inboxPreviews).forEach((preview) => {
         if (!merged.some((item) => item.id === preview.id)) {
            merged.push(preview);
         }
      });

      return merged.sort((a, b) => {
         const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
         const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

         return bTime - aTime;
      });
   }, [conversations, realtime]);

   const { data, setData, errors, reset, setError, clearErrors } = useForm<{
      body: string;
      attachment: File | null;
   }>({
      body: '',
      attachment: null,
   });
   const [fileKey, setFileKey] = useState(0);

   const applyInboxFilters = (next: Partial<Filters>) => {
      const merged = { ...filters, ...next };
      router.get(
         activeConversation ? route('messages.show', activeConversation.id) : route('messages.index'),
         {
            q: merged.q || undefined,
            filter: merged.filter || undefined,
            mq: merged.mq || undefined,
         },
         { preserveState: true, preserveScroll: true, replace: true },
      );
   };

   const submitInboxSearch = (e: FormEvent) => {
      e.preventDefault();
      applyInboxFilters({ q: inboxQuery.trim() || null });
   };

   const submitThreadSearch = (e: FormEvent) => {
      e.preventDefault();
      if (!activeConversation) return;
      applyInboxFilters({ mq: threadQuery.trim() || null });
   };

   const submit = (e: FormEvent) => {
      e.preventDefault();
      if (!activeConversation) {
         return;
      }

      const body = data.body.trim();
      const attachment = data.attachment;
      if (!body && !attachment) {
         return;
      }

      clearErrors();

      const tempId = -Date.now();
      const pending: ChatMessageItem = {
         id: tempId,
         body: body || null,
         attachment: attachment ? URL.createObjectURL(attachment) : null,
         attachment_name: attachment?.name ?? null,
         attachment_type: attachment
            ? attachment.type.startsWith('video/')
               ? 'video'
               : attachment.type === 'application/pdf'
                 ? 'pdf'
                 : 'image'
            : null,
         created_at: new Date().toISOString(),
         is_mine: true,
         can_delete: false,
         sender: {
            id: auth.user?.id,
            name: auth.user?.name,
            photo: auth.user?.photo ?? null,
            role: auth.user?.role,
         },
      };

      setLiveMessages((current) => [...current, pending]);
      realtime?.mergeInboxPreview({
         ...activeConversation,
         last_message_at: pending.created_at,
         preview: pending.body || pending.attachment_name || 'Attachment',
         preview_sender: auth.user?.name,
         unread: false,
      });

      const formData = new FormData();
      if (body) {
         formData.append('body', body);
      }
      if (attachment) {
         formData.append('attachment', attachment);
      }

      reset('body', 'attachment');
      setFileKey((current) => current + 1);

      void fetch(route('messages.store', activeConversation.id), {
         method: 'POST',
         headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
         },
         credentials: 'same-origin',
         body: formData,
      }).then(async (response) => {
         const payload = (await response.json().catch(() => null)) as
            | {
                 message?: ChatMessageItem;
                 errors?: Record<string, string[]>;
              }
            | null;

         if (!response.ok) {
            setLiveMessages((current) => current.filter((item) => item.id !== tempId));
            setData({ body, attachment });
            const fieldErrors = payload?.errors ?? {};
            Object.entries(fieldErrors).forEach(([field, messages]) => {
               if (messages[0]) {
                  setError(field as 'body' | 'attachment', messages[0]);
               }
            });
            return;
         }

         if (!payload?.message) {
            return;
         }

         const sent: ChatMessageItem = {
            ...payload.message,
            is_mine: true,
            can_delete: payload.message.can_delete ?? true,
         };

         setLiveMessages((current) => {
            const withoutPending = current.filter((item) => item.id !== tempId);
            if (withoutPending.some((item) => item.id === sent.id)) {
               return withoutPending;
            }

            return [...withoutPending, sent];
         });

         realtime?.mergeInboxPreview({
            ...activeConversation,
            last_message_at: sent.created_at ?? pending.created_at,
            preview: sent.body || sent.attachment_name || 'Attachment',
            preview_sender: auth.user?.name,
            unread: false,
         });
      });
   };

   const threadAction = (name: 'resolve' | 'reopen' | 'mute') => {
      if (!activeConversation) return;
      const routeName =
         name === 'resolve' ? 'messages.resolve' : name === 'reopen' ? 'messages.reopen' : 'messages.mute';

      router.post(route(routeName, activeConversation.id), {}, { preserveScroll: true });
   };

   const unpinThread = () => {
      if (!activeConversation) return;
      router.delete(route('messages.unpin', activeConversation.id), { preserveScroll: true });
   };

   const pinMessage = (messageId: number) => {
      if (!activeConversation) return;
      router.post(
         route('messages.pin', { conversation: activeConversation.id, message: messageId }),
         {},
         { preserveScroll: true },
      );
   };

   const deleteMessage = (messageId: number) => {
      if (!activeConversation) return;
      if (!window.confirm('Delete this message?')) return;
      router.delete(
         route('messages.message.destroy', { conversation: activeConversation.id, message: messageId }),
         { preserveScroll: true },
      );
   };

   return (
      <DashboardLayout
         variant={isLearner ? 'learner' : 'admin'}
         headTitle="Messages"
         lockViewport
      >
         <Head title="Messages" />

         <div className="mb-3 shrink-0">
            <h1 className="text-2xl font-semibold text-[#01123A]">Messages</h1>
            <p className="mt-1 text-sm text-muted-foreground">
               {isAdmin
                  ? 'Private Academy messages with any student, plus course and class chats.'
                  : 'Private messages with your instructor, class discussions, and Academy support.'}
            </p>
         </div>

         <div className="grid min-h-0 flex-1 grid-rows-[auto_minmax(0,1fr)] overflow-hidden rounded-xl border border-border/70 bg-white lg:grid-cols-[320px_minmax(0,1fr)] lg:grid-rows-none">
            <aside className="flex max-h-[38vh] min-h-0 flex-col border-b border-border/70 lg:max-h-none lg:border-r lg:border-b-0">
               <div className="shrink-0 border-b border-border/60 px-4 py-3">
                  <p className="text-sm font-medium">Inbox</p>
                  <form onSubmit={submitInboxSearch} className="mt-2 flex gap-2">
                     <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input
                           value={inboxQuery}
                           onChange={(e) => setInboxQuery(e.target.value)}
                           placeholder="Search conversations…"
                           className="h-9 pl-8"
                        />
                     </div>
                     <Button type="submit" variant="outline" size="sm">
                        Go
                     </Button>
                  </form>
                  <div className="mt-2 flex flex-wrap gap-1">
                     {inboxFilters.map((item) => (
                        <Button
                           key={item.value || 'all'}
                           type="button"
                           size="sm"
                           variant={(filters.filter ?? '') === item.value ? 'default' : 'outline'}
                           className="h-7 px-2 text-xs"
                           onClick={() => applyInboxFilters({ filter: item.value || null })}
                        >
                           {item.label}
                        </Button>
                     ))}
                  </div>
                  {canStartAcademyChat ? <AcademyComposer /> : null}
               </div>
               <div className="min-h-0 flex-1 overflow-y-auto">
                  {mergedConversations.length === 0 ? (
                     <p className="px-4 py-8 text-sm text-muted-foreground">
                        No conversations match your filters yet.
                     </p>
                  ) : (
                     mergedConversations.map((conversation) => (
                        <Link
                           key={conversation.id}
                           href={inboxHref(conversation.id, filters)}
                           className={cn(
                              'block border-b border-border/40 px-4 py-3 transition-colors hover:bg-muted/40',
                              activeConversation?.id === conversation.id && 'bg-muted/60',
                           )}
                        >
                           <div className="flex items-start justify-between gap-2">
                              <div className="min-w-0">
                                 <div className="flex items-center gap-2">
                                    <p className={cn('truncate text-sm font-medium', conversation.unread && 'text-[#01123A]')}>
                                       {conversation.label}
                                    </p>
                                    {conversation.is_resolved && (
                                       <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800">
                                          Resolved
                                       </span>
                                    )}
                                    {conversation.is_muted && <BellOff className="h-3 w-3 text-muted-foreground" />}
                                 </div>
                                 <p className="truncate text-xs text-muted-foreground">{conversation.course_title}</p>
                                 {conversation.preview && (
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                       {conversation.preview_sender ? `${conversation.preview_sender}: ` : ''}
                                       {conversation.preview}
                                    </p>
                                 )}
                              </div>
                              <div className="shrink-0 text-right">
                                 {conversation.unread && <span className="inline-block h-2 w-2 rounded-full bg-[#8C2A23]" />}
                                 <p className="mt-1 text-[10px] text-muted-foreground">{formatTime(conversation.last_message_at)}</p>
                              </div>
                           </div>
                        </Link>
                     ))
                  )}
               </div>
            </aside>

            <section className="flex min-h-0 flex-col">
               {!activeConversation ? (
                  <div className="flex flex-1 items-center justify-center px-6 text-center text-sm text-muted-foreground">
                     Select a conversation to start messaging.
                  </div>
               ) : (
                  <>
                     <div className="shrink-0 border-b border-border/60 px-4 py-3">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                           <div>
                              <p className="font-medium text-[#01123A]">{activeConversation.label}</p>
                              <p className="text-xs text-muted-foreground">
                                 {activeConversation.type === 'group'
                                    ? `Class chat · ${activeConversation.course_title ?? 'Course'}`
                                    : activeConversation.type === 'academy'
                                      ? 'Private message with Academy'
                                      : `Private message · ${activeConversation.course_title ?? 'Course'}`}
                              </p>
                           </div>
                           <div className="flex flex-wrap gap-2">
                              {!auth.user || auth.user.role === 'admin' ? null : (
                                 <Button type="button" variant="outline" size="sm" onClick={() => threadAction('mute')}>
                                    {activeConversation.is_muted ? 'Unmute' : 'Mute'}
                                 </Button>
                              )}
                              {activeConversation.can_resolve && (
                                 activeConversation.is_resolved ? (
                                    <Button type="button" variant="outline" size="sm" onClick={() => threadAction('reopen')}>
                                       Reopen
                                    </Button>
                                 ) : (
                                    <Button type="button" variant="outline" size="sm" onClick={() => threadAction('resolve')}>
                                       Mark resolved
                                    </Button>
                                 )
                              )}
                              {activeConversation.can_pin && activeConversation.pinned_message && (
                                 <Button type="button" variant="outline" size="sm" onClick={unpinThread}>
                                    Unpin
                                 </Button>
                              )}
                           </div>
                        </div>
                        {activeConversation.is_resolved && (
                           <p className="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                              This direct message is marked resolved. Students cannot reply until it is reopened.
                           </p>
                        )}
                        <form onSubmit={submitThreadSearch} className="mt-3 flex gap-2">
                           <div className="relative flex-1">
                              <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                              <Input
                                 value={threadQuery}
                                 onChange={(e) => setThreadQuery(e.target.value)}
                                 placeholder="Search in this conversation…"
                                 className="h-9 pl-8"
                              />
                           </div>
                           <Button type="submit" variant="outline" size="sm">
                              Search
                           </Button>
                           {filters.mq && (
                              <Button
                                 type="button"
                                 variant="ghost"
                                 size="sm"
                                 onClick={() => {
                                    setThreadQuery('');
                                    applyInboxFilters({ mq: null });
                                 }}
                              >
                                 <X className="h-4 w-4" />
                              </Button>
                           )}
                        </form>
                     </div>

                     {activeConversation.pinned_message && (
                        <div className="max-h-40 shrink-0 overflow-y-auto border-b border-border/60 bg-amber-50 px-4 py-3">
                           <p className="mb-2 flex items-center gap-1 text-xs font-medium text-amber-900">
                              <Pin className="h-3 w-3" /> Pinned message
                           </p>
                           <MessageBubble message={activeConversation.pinned_message} />
                        </div>
                     )}

                     <div ref={threadRef} className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                        {liveMessages.length === 0 ? (
                           <p className="text-sm text-muted-foreground">
                              {filters.mq ? 'No messages match your search.' : 'No messages yet.'}
                           </p>
                        ) : (
                           liveMessages
                              .filter((message) => message.id !== activeConversation.pinned_message?.id)
                              .map((message) => (
                              <MessageBubble
                                 key={message.id}
                                 message={message}
                                 canPin={activeConversation.can_pin}
                                 onPin={pinMessage}
                                 onDelete={deleteMessage}
                              />
                           ))
                        )}
                     </div>

                     {activeConversation.can_send ? (
                        <form onSubmit={submit} className="shrink-0 border-t border-border/60 bg-white px-4 py-3" encType="multipart/form-data">
                           <Textarea
                              rows={3}
                              className="max-h-28 resize-none"
                              placeholder="Write a message…"
                              value={data.body}
                              onChange={(e) => setData('body', e.target.value)}
                              onKeyDown={(e) => {
                                 if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    e.currentTarget.form?.requestSubmit();
                                 }
                              }}
                           />
                           <InputError message={errors.body} />
                           <div className="mt-2 flex flex-wrap items-center gap-2">
                              <Input
                                 key={fileKey}
                                 type="file"
                                 accept="image/*,video/mp4,video/webm,video/quicktime,application/pdf"
                                 className="max-w-xs"
                                 onChange={(e) => setData('attachment', e.target.files?.[0] ?? null)}
                              />
                              <InputError message={errors.attachment} />
                              <p className="text-xs text-muted-foreground">Images, videos (50MB), or PDFs · Enter to send</p>
                              <Button type="submit">Send</Button>
                           </div>
                        </form>
                     ) : (
                        <div className="shrink-0 border-t border-border/60 px-4 py-3 text-sm text-muted-foreground">
                           {activeConversation.is_resolved
                              ? activeConversation.type === 'academy'
                                 ? 'This conversation is resolved. You cannot send new messages until Academy reopens it.'
                                 : 'This conversation is resolved. You cannot send new messages until your instructor reopens it.'
                              : 'You cannot send messages in this conversation right now. Course access may have ended.'}
                        </div>
                     )}
                  </>
               )}
            </section>
         </div>
      </DashboardLayout>
   );
}
