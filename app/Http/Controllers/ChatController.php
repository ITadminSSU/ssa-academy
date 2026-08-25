<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Course\Course;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $conversations = $this->chat->inboxFor(
            $request->user(),
            $request->query('q'),
            $request->query('filter'),
        );
        $activeId = $request->integer('conversation') ?: null;
        $active = null;

        if ($activeId) {
            $conversation = ChatConversation::query()->findOrFail($activeId);
            abort_unless($this->chat->canView($request->user(), $conversation), 403);
            $active = $this->chat->conversationPayload($conversation, $request->user(), $request->query('mq'));
        }

        return Inertia::render('messages/index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
            'filters' => [
                'q' => $request->query('q'),
                'filter' => $request->query('filter'),
                'mq' => $request->query('mq'),
            ],
        ]);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);

        return Inertia::render('messages/index', [
            'conversations' => $this->chat->inboxFor(
                $request->user(),
                $request->query('q'),
                $request->query('filter'),
            ),
            'activeConversation' => $this->chat->conversationPayload(
                $conversation,
                $request->user(),
                $request->query('mq'),
            ),
            'filters' => [
                'q' => $request->query('q'),
                'filter' => $request->query('filter'),
                'mq' => $request->query('mq'),
            ],
        ]);
    }

    public function store(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canSend($request->user(), $conversation), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => [
                'nullable',
                'file',
                'max:51200',
                Rule::file()->types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'pdf']),
            ],
        ]);

        $this->chat->sendMessage(
            $request->user(),
            $conversation,
            $data['body'] ?? null,
            $request->file('attachment'),
        );

        return back();
    }

    public function presence(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', Rule::exists(ChatConversation::class, 'id')],
            'visible' => ['required', 'boolean'],
        ]);

        $conversationId = isset($data['conversation_id']) ? (int) $data['conversation_id'] : null;

        if ($conversationId) {
            $conversation = ChatConversation::query()->findOrFail($conversationId);
            abort_unless($this->chat->canView($request->user(), $conversation), 403);
        }

        app(\App\Services\Chat\ChatPresenceService::class)->update(
            $request->user(),
            $conversationId,
            (bool) $data['visible'],
        );

        if ($conversationId && $data['visible']) {
            $conversation = ChatConversation::query()->findOrFail($conversationId);
            $this->chat->markConversationRead($conversation, $request->user());
        }

        return response()->json(['ok' => true]);
    }

    public function unread(Request $request)
    {
        return response()->json([
            'messages_unread_count' => $this->chat->unreadCount($request->user()),
        ]);
    }

    public function read(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->markConversationRead($conversation, $request->user());

        return response()->json([
            'messages_unread_count' => $this->chat->unreadCount($request->user()),
        ]);
    }

    public function resolve(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->resolveConversation($request->user(), $conversation);

        return back();
    }

    public function reopen(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->reopenConversation($request->user(), $conversation);

        return back();
    }

    public function mute(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->toggleMute($request->user(), $conversation);

        return back();
    }

    public function pin(Request $request, ChatConversation $conversation, ChatMessage $message)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->pinMessage($request->user(), $conversation, $message);

        return back();
    }

    public function unpin(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->unpinMessage($request->user(), $conversation);

        return back();
    }

    public function destroyMessage(Request $request, ChatConversation $conversation, ChatMessage $message)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);
        $this->chat->deleteMessage($request->user(), $conversation, $message);

        return back();
    }

    public function openDirect(Request $request, Course $course)
    {
        $conversation = $this->chat->openDirect($request->user(), $course);

        return redirect()->route('messages.show', $conversation);
    }

    public function openGroup(Request $request, Course $course)
    {
        $conversation = $this->chat->openGroup($request->user(), $course);

        return redirect()->route('messages.show', $conversation);
    }
}
