<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\Course\Course;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $conversations = $this->chat->inboxFor($request->user());
        $activeId = $request->integer('conversation') ?: null;
        $active = null;

        if ($activeId) {
            $conversation = ChatConversation::query()->findOrFail($activeId);
            abort_unless($this->chat->canView($request->user(), $conversation), 403);
            $active = $this->chat->conversationPayload($conversation, $request->user());
        }

        return Inertia::render('messages/index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
        ]);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);

        return Inertia::render('messages/index', [
            'conversations' => $this->chat->inboxFor($request->user()),
            'activeConversation' => $this->chat->conversationPayload($conversation, $request->user()),
        ]);
    }

    public function store(Request $request, ChatConversation $conversation)
    {
        abort_unless($this->chat->canSend($request->user(), $conversation), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->chat->sendMessage(
            $request->user(),
            $conversation,
            $data['body'] ?? null,
            $request->file('attachment'),
        );

        return redirect()->route('messages.show', $conversation);
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
