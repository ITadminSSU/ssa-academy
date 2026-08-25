import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type ConversationListItem = {
   id: number;
   type: 'direct' | 'group';
   course_id: number;
   course_title?: string | null;
   label: string;
   last_message_at?: string | null;
   preview?: string | null;
   preview_sender?: string | null;
   unread?: boolean;
};

type ChatMessageItem = {
   id: number;
   body?: string | null;
   attachment?: string | null;
   attachment_name?: string | null;
   created_at?: string | null;
   is_mine?: boolean;
   sender?: { id?: number; name?: string; photo?: string | null; role?: string };
};

type ActiveConversation = ConversationListItem & {
   messages: ChatMessageItem[];
   can_send: boolean;
};

type Props = SharedData & {
   conversations: ConversationListItem[];
   activeConversation: ActiveConversation | null;
};

function formatTime(value?: string | null) {
   if (!value) return '';
   return new Date(value).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
   });
}

export default function MessagesIndex() {
   const { conversations, activeConversation, auth } = usePage<Props>().props;
   const isLearner = auth.user?.role === 'student';

   const { data, setData, post, processing, errors, reset } = useForm<{
      body: string;
      attachment: File | null;
   }>({
      body: '',
      attachment: null,
   });

   const submit = (e: FormEvent) => {
      e.preventDefault();
      if (!activeConversation) return;

      post(route('messages.store', activeConversation.id), {
         forceFormData: true,
         onSuccess: () => reset('body', 'attachment'),
         preserveScroll: true,
      });
   };

   return (
      <DashboardLayout variant={isLearner ? 'learner' : 'admin'} headTitle="Messages">
         <Head title="Messages" />

         <div className="mb-4">
            <h1 className="text-2xl font-semibold text-[#01123A]">Messages</h1>
            <p className="mt-1 text-sm text-muted-foreground">
               Private messages with your instructor and class discussions for enrolled courses.
            </p>
         </div>

         <div className="grid min-h-[560px] overflow-hidden rounded-xl border border-border/70 bg-white lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside className="border-b border-border/70 lg:border-r lg:border-b-0">
               <div className="border-b border-border/60 px-4 py-3 text-sm font-medium">Inbox</div>
               <div className="max-h-[520px] overflow-y-auto">
                  {conversations.length === 0 ? (
                     <p className="px-4 py-8 text-sm text-muted-foreground">
                        No conversations yet. Open Messages from a course to message your instructor or join class chat.
                     </p>
                  ) : (
                     conversations.map((conversation) => (
                        <Link
                           key={conversation.id}
                           href={route('messages.show', conversation.id)}
                           className={cn(
                              'block border-b border-border/40 px-4 py-3 transition-colors hover:bg-muted/40',
                              activeConversation?.id === conversation.id && 'bg-muted/60',
                           )}
                        >
                           <div className="flex items-start justify-between gap-2">
                              <div className="min-w-0">
                                 <p className={cn('truncate text-sm font-medium', conversation.unread && 'text-[#01123A]')}>
                                    {conversation.label}
                                 </p>
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

            <section className="flex min-h-[420px] flex-col">
               {!activeConversation ? (
                  <div className="flex flex-1 items-center justify-center px-6 text-center text-sm text-muted-foreground">
                     Select a conversation to start messaging.
                  </div>
               ) : (
                  <>
                     <div className="border-b border-border/60 px-4 py-3">
                        <p className="font-medium text-[#01123A]">{activeConversation.label}</p>
                        <p className="text-xs text-muted-foreground">
                           {activeConversation.type === 'group' ? 'Class chat' : 'Private message'} · {activeConversation.course_title}
                        </p>
                     </div>

                     <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                        {activeConversation.messages.map((message) => (
                           <div key={message.id} className={cn('flex gap-2', message.is_mine ? 'justify-end' : 'justify-start')}>
                              {!message.is_mine && (
                                 <Avatar className="h-8 w-8">
                                    <AvatarImage src={message.sender?.photo || undefined} />
                                    <AvatarFallback>{message.sender?.name?.charAt(0) || '?'}</AvatarFallback>
                                 </Avatar>
                              )}
                              <div
                                 className={cn(
                                    'max-w-[75%] rounded-2xl px-3 py-2 text-sm',
                                    message.is_mine ? 'bg-[#01123A] text-white' : 'bg-muted text-foreground',
                                 )}
                              >
                                 {!message.is_mine && <p className="mb-1 text-[11px] font-medium opacity-70">{message.sender?.name}</p>}
                                 {message.body && <p className="whitespace-pre-wrap">{message.body}</p>}
                                 {message.attachment && (
                                    <a href={message.attachment} target="_blank" rel="noopener noreferrer" className="mt-2 block">
                                       <img src={message.attachment} alt={message.attachment_name || 'Attachment'} className="max-h-48 rounded-lg" />
                                    </a>
                                 )}
                                 <p className={cn('mt-1 text-[10px]', message.is_mine ? 'text-white/70' : 'text-muted-foreground')}>
                                    {formatTime(message.created_at)}
                                 </p>
                              </div>
                           </div>
                        ))}
                     </div>

                     {activeConversation.can_send ? (
                        <form onSubmit={submit} className="border-t border-border/60 px-4 py-3" encType="multipart/form-data">
                           <Textarea
                              rows={3}
                              placeholder="Write a message…"
                              value={data.body}
                              onChange={(e) => setData('body', e.target.value)}
                           />
                           <InputError message={errors.body} />
                           <div className="mt-2 flex flex-wrap items-center gap-2">
                              <Input
                                 type="file"
                                 accept="image/*"
                                 className="max-w-xs"
                                 onChange={(e) => setData('attachment', e.target.files?.[0] ?? null)}
                              />
                              <InputError message={errors.attachment} />
                              <Button type="submit" disabled={processing}>
                                 {processing ? 'Sending…' : 'Send'}
                              </Button>
                           </div>
                        </form>
                     ) : (
                        <div className="border-t border-border/60 px-4 py-3 text-sm text-muted-foreground">
                           You cannot send messages in this conversation right now. Course access may have ended.
                        </div>
                     )}
                  </>
               )}
            </section>
         </div>
      </DashboardLayout>
   );
}
