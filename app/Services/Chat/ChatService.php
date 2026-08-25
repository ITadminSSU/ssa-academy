<?php

namespace App\Services\Chat;

use App\Enums\ChatAttachmentType;
use App\Enums\ChatConversationType;
use App\Enums\ChatParticipantRole;
use App\Mail\ChatMessageMail;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\Course\Course;
use App\Models\Course\CourseEnrollment;
use App\Models\User;
use App\Support\TransactionalMailSender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        private ChatAccessService $access,
        private TransactionalMailSender $mailSender,
        private \App\Services\MediaService $mediaService,
    ) {}

    public function syncEnrollmentAccess(User $user, Course $course, ?CourseEnrollment $enrollment = null): void
    {
        $hasAccess = $this->access->canAccessCourseMessaging($user, $course, $enrollment);

        if ($hasAccess) {
            $this->ensureGroupConversation($course);
            $this->ensureDirectConversation($user, $course);
            $this->setParticipantActiveForCourse($user, $course, true);
        } else {
            $this->setParticipantActiveForCourse($user, $course, false);
        }
    }

    public function syncActiveEnrollmentsForUser(User $user): void
    {
        CourseEnrollment::query()
            ->where('user_id', $user->id)
            ->with(['course', 'subscription'])
            ->chunkById(50, function ($enrollments) use ($user) {
                foreach ($enrollments as $enrollment) {
                    if ($enrollment->course) {
                        $this->syncEnrollmentAccess($user, $enrollment->course, $enrollment);
                    }
                }
            });
    }

    public function ensureGroupConversation(Course $course): ChatConversation
    {
        $course->loadMissing('instructor.user');

        $conversation = ChatConversation::query()->firstOrCreate(
            [
                'type' => ChatConversationType::Group,
                'course_id' => $course->id,
            ],
            [
                'title' => 'Class: '.$course->title,
            ]
        );

        $instructorUser = $course->instructor?->user;
        if ($instructorUser) {
            $this->upsertParticipant($conversation, $instructorUser, ChatParticipantRole::Instructor, true);
        }

        CourseEnrollment::query()
            ->where('course_id', $course->id)
            ->with(['user', 'subscription'])
            ->chunkById(100, function ($enrollments) use ($course, $conversation) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->user) {
                        continue;
                    }

                    if ($this->access->canAccessCourseMessaging($enrollment->user, $course, $enrollment)) {
                        $this->upsertParticipant($conversation, $enrollment->user, ChatParticipantRole::Student, true);
                    }
                }
            });

        return $conversation;
    }

    public function ensureDirectConversation(User $student, Course $course): ChatConversation
    {
        $course->loadMissing('instructor.user');

        $conversation = ChatConversation::query()->firstOrCreate(
            [
                'type' => ChatConversationType::Direct,
                'course_id' => $course->id,
                'student_user_id' => $student->id,
            ],
            [
                'title' => $course->title,
            ]
        );

        $this->upsertParticipant($conversation, $student, ChatParticipantRole::Student, true);

        $instructorUser = $course->instructor?->user;
        if ($instructorUser) {
            $this->upsertParticipant($conversation, $instructorUser, ChatParticipantRole::Instructor, true);
        }

        return $conversation;
    }

    public function openDirect(User $user, Course $course): ChatConversation
    {
        abort_unless($this->access->canAccessCourseMessaging($user, $course), 403);

        if ($this->access->isCourseInstructor($user, $course)) {
            abort(422, 'Instructors should open a student thread from the inbox.');
        }

        return $this->ensureDirectConversation($user, $course);
    }

    public function openGroup(User $user, Course $course): ChatConversation
    {
        abort_unless($this->access->canAccessCourseMessaging($user, $course), 403);

        $conversation = $this->ensureGroupConversation($course);
        $this->syncEnrollmentAccess($user, $course);

        return $conversation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inboxFor(User $user, ?string $query = null, ?string $filter = null): array
    {
        if ($user->role === 'student') {
            $this->syncActiveEnrollmentsForUser($user);
        }

        if ($this->access->isAdmin($user)) {
            $items = $this->adminInbox($user);
        } else {
            $participantRows = ChatParticipant::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->with([
                    'conversation.course.instructor.user',
                    'conversation.student',
                    'conversation.messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name'),
                ])
                ->get()
                ->sortByDesc(fn (ChatParticipant $p) => $p->conversation?->last_message_at ?? $p->conversation?->created_at)
                ->values();

            $items = $participantRows
                ->map(fn (ChatParticipant $participant) => $this->formatConversationListItem($participant, $user))
                ->filter()
                ->values()
                ->all();
        }

        return $this->filterInboxItems($items, $query, $filter);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminInbox(User $viewer): array
    {
        return ChatConversation::query()
            ->with([
                'course.instructor.user',
                'student:id,name',
                'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name'),
            ])
            ->latest('last_message_at')
            ->limit(200)
            ->get()
            ->map(fn (ChatConversation $conversation) => $this->formatConversationRow($conversation, $viewer))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filterInboxItems(array $items, ?string $query, ?string $filter): array
    {
        $filtered = collect($items);

        if ($filter === 'unread') {
            $filtered = $filtered->where('unread', true);
        } elseif ($filter === 'direct') {
            $filtered = $filtered->where('type', ChatConversationType::Direct->value);
        } elseif ($filter === 'group') {
            $filtered = $filtered->where('type', ChatConversationType::Group->value);
        }

        if (filled($query)) {
            $needle = mb_strtolower(trim($query));
            $filtered = $filtered->filter(function (array $item) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $item['label'] ?? '',
                    $item['course_title'] ?? '',
                    $item['preview'] ?? '',
                    $item['preview_sender'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            });
        }

        return $filtered->values()->all();
    }

    public function canView(User $user, ChatConversation $conversation): bool
    {
        if ($this->access->isAdmin($user)) {
            return true;
        }

        return ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canSend(User $user, ChatConversation $conversation): bool
    {
        if ($this->access->isAdmin($user)) {
            return true;
        }

        $participant = ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $participant) {
            return false;
        }

        $conversation->loadMissing('course');

        if (! $this->access->canAccessCourseMessaging($user, $conversation->course)) {
            return false;
        }

        if (
            $conversation->type === ChatConversationType::Direct
            && $conversation->isResolved()
            && $user->role === 'student'
        ) {
            return false;
        }

        return true;
    }

    public function canModerate(User $user, ChatConversation $conversation): bool
    {
        $conversation->loadMissing('course');

        return $this->access->canModerate($user, $conversation->course);
    }

    public function canDeleteMessage(User $user, ChatConversation $conversation, ChatMessage $message): bool
    {
        if ((int) $message->chat_conversation_id !== (int) $conversation->id) {
            return false;
        }

        if ((int) $message->user_id === (int) $user->id) {
            return true;
        }

        return $this->canModerate($user, $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function conversationPayload(
        ChatConversation $conversation,
        User $viewer,
        ?string $messageQuery = null,
    ): array {
        $conversation->load(['course.instructor.user', 'student:id,name,photo', 'pinnedMessage.sender:id,name,photo,role']);

        $messagesQuery = ChatMessage::query()
            ->where('chat_conversation_id', $conversation->id)
            ->with('sender:id,name,photo,role')
            ->orderBy('id');

        if (filled($messageQuery)) {
            $messagesQuery->where('body', 'like', '%'.addcslashes(trim($messageQuery), '%_\\').'%');
        }

        $messages = $messagesQuery
            ->limit(200)
            ->get()
            ->map(fn (ChatMessage $message) => $this->formatMessage($message, $viewer, $conversation))
            ->values()
            ->all();

        $this->markRead($conversation, $viewer);

        $participant = ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $viewer->id)
            ->first();

        $canModerate = $this->canModerate($viewer, $conversation);

        return [
            ...$this->formatConversationRow($conversation, $viewer, $participant),
            'messages' => $messages,
            'can_send' => $this->canSend($viewer, $conversation),
            'can_moderate' => $canModerate,
            'can_resolve' => $canModerate && $conversation->type === ChatConversationType::Direct,
            'can_pin' => $canModerate && $conversation->type === ChatConversationType::Group,
            'is_muted' => (bool) ($participant?->is_muted),
            'pinned_message' => $conversation->pinnedMessage
                ? $this->formatMessage($conversation->pinnedMessage, $viewer, $conversation)
                : null,
        ];
    }

    public function sendMessage(
        User $sender,
        ChatConversation $conversation,
        ?string $body,
        ?UploadedFile $attachment = null,
    ): ChatMessage {
        abort_unless($this->canSend($sender, $conversation), 403);

        if (blank($body) && ! $attachment) {
            abort(422, 'Please enter a message or attach a file.');
        }

        return DB::transaction(function () use ($sender, $conversation, $body, $attachment) {
            if (
                $conversation->type === ChatConversationType::Direct
                && $conversation->isResolved()
                && $this->canModerate($sender, $conversation)
            ) {
                $conversation->update([
                    'resolved_at' => null,
                    'resolved_by' => null,
                ]);
            }

            $message = ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'user_id' => $sender->id,
                'body' => $body,
            ]);

            if ($attachment) {
                $attachmentType = $this->detectAttachmentType($attachment);
                $url = $this->mediaService->addNewDeletePrev($message, $attachment, 'attachment');
                $message->update([
                    'attachment' => $url,
                    'attachment_name' => $attachment->getClientOriginalName(),
                    'attachment_type' => $attachmentType,
                ]);
            }

            $conversation->update(['last_message_at' => now()]);

            $this->notifyParticipants($sender, $conversation, $message->fresh(['sender']));

            return $message->fresh(['sender:id,name,photo,role']);
        });
    }

    public function resolveConversation(User $user, ChatConversation $conversation): void
    {
        abort_unless($this->canModerate($user, $conversation), 403);
        abort_unless($conversation->type === ChatConversationType::Direct, 422, 'Only direct messages can be resolved.');

        $conversation->update([
            'resolved_at' => now(),
            'resolved_by' => $user->id,
        ]);
    }

    public function reopenConversation(User $user, ChatConversation $conversation): void
    {
        abort_unless($this->canModerate($user, $conversation), 403);
        abort_unless($conversation->type === ChatConversationType::Direct, 422, 'Only direct messages can be reopened.');

        $conversation->update([
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    public function pinMessage(User $user, ChatConversation $conversation, ChatMessage $message): void
    {
        abort_unless($this->canModerate($user, $conversation), 403);
        abort_unless($conversation->type === ChatConversationType::Group, 422, 'Only class chats support pinned messages.');
        abort_unless((int) $message->chat_conversation_id === (int) $conversation->id, 422);

        $conversation->update(['pinned_message_id' => $message->id]);
    }

    public function unpinMessage(User $user, ChatConversation $conversation): void
    {
        abort_unless($this->canModerate($user, $conversation), 403);

        $conversation->update(['pinned_message_id' => null]);
    }

    public function toggleMute(User $user, ChatConversation $conversation): bool
    {
        abort_unless($this->canView($user, $conversation), 403);
        abort_unless(! $this->access->isAdmin($user), 422, 'Admins cannot mute conversations.');

        $participant = ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $participant->update(['is_muted' => ! $participant->is_muted]);

        return (bool) $participant->fresh()->is_muted;
    }

    public function deleteMessage(User $user, ChatConversation $conversation, ChatMessage $message): void
    {
        abort_unless($this->canDeleteMessage($user, $conversation, $message), 403);

        DB::transaction(function () use ($conversation, $message) {
            if ((int) $conversation->pinned_message_id === (int) $message->id) {
                $conversation->update(['pinned_message_id' => null]);
            }

            $message->delete();
        });
    }

    public function unreadCount(User $user): int
    {
        if ($this->access->isAdmin($user)) {
            return 0;
        }

        return ChatParticipant::query()
            ->where('chat_participants.user_id', $user->id)
            ->where('chat_participants.is_active', true)
            ->join('chat_conversations', 'chat_conversations.id', '=', 'chat_participants.chat_conversation_id')
            ->whereNotNull('chat_conversations.last_message_at')
            ->where(function ($query) {
                $query->whereNull('chat_participants.last_read_at')
                    ->orWhereColumn('chat_conversations.last_message_at', '>', 'chat_participants.last_read_at');
            })
            ->count();
    }

    private function detectAttachmentType(UploadedFile $file): ChatAttachmentType
    {
        $mime = (string) $file->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            return ChatAttachmentType::Image;
        }

        if (str_starts_with($mime, 'video/')) {
            return ChatAttachmentType::Video;
        }

        if ($mime === 'application/pdf') {
            return ChatAttachmentType::Pdf;
        }

        abort(422, 'Attachments must be an image, video, or PDF.');
    }

    private function notifyParticipants(User $sender, ChatConversation $conversation, ChatMessage $message): void
    {
        $recipients = ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', '!=', $sender->id)
            ->where('is_active', true)
            ->where('is_muted', false)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof User) {
                continue;
            }

            try {
                $this->mailSender->send(
                    $recipient,
                    new ChatMessageMail($conversation, $message),
                    'chat_new_message'
                );
            } catch (\Throwable $e) {
                Log::warning('chat_new_message notify failed', [
                    'recipient_id' => $recipient->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markRead(ChatConversation $conversation, User $user): void
    {
        ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    private function setParticipantActiveForCourse(User $user, Course $course, bool $active): void
    {
        $conversationIds = ChatConversation::query()
            ->where('course_id', $course->id)
            ->where(function ($query) use ($user) {
                $query->where('type', ChatConversationType::Group)
                    ->orWhere('student_user_id', $user->id);
            })
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return;
        }

        ChatParticipant::query()
            ->where('user_id', $user->id)
            ->whereIn('chat_conversation_id', $conversationIds)
            ->update(['is_active' => $active]);
    }

    private function upsertParticipant(
        ChatConversation $conversation,
        User $user,
        ChatParticipantRole $role,
        bool $active,
    ): ChatParticipant {
        return ChatParticipant::query()->updateOrCreate(
            [
                'chat_conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'is_active' => $active,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatConversationListItem(ChatParticipant $participant, User $viewer): ?array
    {
        $conversation = $participant->conversation;
        if (! $conversation) {
            return null;
        }

        return $this->formatConversationRow($conversation, $viewer, $participant);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversationRow(
        ChatConversation $conversation,
        User $viewer,
        ?ChatParticipant $participant = null,
    ): array {
        $latest = $conversation->messages->first();
        $participant ??= ChatParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $viewer->id)
            ->first();

        $unread = $participant && $latest
            && ($participant->last_read_at === null || $latest->created_at->gt($participant->last_read_at));

        $label = $conversation->type === ChatConversationType::Group
            ? ($conversation->title ?? 'Class chat')
            : ($conversation->student?->name ?? 'Direct message');

        if ($conversation->type === ChatConversationType::Direct && $conversation->course && $this->access->isCourseInstructor($viewer, $conversation->course)) {
            $label = $conversation->student?->name ?? 'Student';
        }

        if ($conversation->type === ChatConversationType::Direct && $viewer->role === 'student') {
            $label = 'Instructor — '.($conversation->course?->title ?? 'Course');
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'course_id' => $conversation->course_id,
            'course_title' => $conversation->course?->title,
            'label' => $label,
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            'preview' => $latest?->body ?: $this->attachmentPreview($latest),
            'preview_sender' => $latest?->sender?->name,
            'unread' => (bool) $unread,
            'is_resolved' => $conversation->isResolved(),
            'is_muted' => (bool) ($participant?->is_muted),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(
        ChatMessage $message,
        User $viewer,
        ChatConversation $conversation,
    ): array {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'attachment' => $message->attachment,
            'attachment_name' => $message->attachment_name,
            'attachment_type' => $message->attachment_type?->value,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'sender' => [
                'id' => $message->sender?->id,
                'name' => $message->sender?->name,
                'photo' => $message->sender?->photo,
                'role' => $message->sender?->role,
            ],
            'is_mine' => (int) $message->user_id === (int) $viewer->id,
            'can_delete' => $this->canDeleteMessage($viewer, $conversation, $message),
            'is_pinned' => (int) ($conversation->pinned_message_id ?? 0) === (int) $message->id,
        ];
    }

    private function attachmentPreview(?ChatMessage $message): ?string
    {
        if (! $message?->attachment) {
            return null;
        }

        return match ($message->attachment_type) {
            ChatAttachmentType::Video => 'Video attachment',
            ChatAttachmentType::Pdf => 'PDF attachment',
            default => 'Image attachment',
        };
    }
}
