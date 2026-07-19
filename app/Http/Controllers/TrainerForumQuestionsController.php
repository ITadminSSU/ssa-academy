<?php

namespace App\Http\Controllers;

use App\Services\Course\CommunityDiscussionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainerForumQuestionsController extends Controller
{
    public function __construct(
        private CommunityDiscussionService $communityDiscussion,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!isAdmin() && empty($user->instructor_id)) {
            abort(403);
        }

        $props = $this->communityDiscussion->listForUser($user, $request->only(['filter', 'course_id']));

        return Inertia::render('dashboard/forum-questions/index', $props);
    }
}
