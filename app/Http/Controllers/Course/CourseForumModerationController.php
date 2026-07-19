<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\CourseForum;
use App\Models\Course\CourseForumReply;
use App\Services\Course\CommunityDiscussionService;
use Illuminate\Http\Request;

class CourseForumModerationController extends Controller
{
    public function __construct(
        private CommunityDiscussionService $communityDiscussion,
    ) {}

    public function resolve(Request $request, CourseForum $forum)
    {
        if (!$this->communityDiscussion->canModerateForum($request->user(), $forum)) {
            abort(403);
        }

        $this->communityDiscussion->resolveForum($forum, $request->user());

        return back()->with('success', 'Thread marked as resolved.');
    }

    public function reopen(Request $request, CourseForum $forum)
    {
        if (!$this->communityDiscussion->canModerateForum($request->user(), $forum)) {
            abort(403);
        }

        $this->communityDiscussion->reopenForum($forum);

        return back()->with('success', 'Thread reopened.');
    }

    public function pinReply(Request $request, CourseForum $forum, CourseForumReply $reply)
    {
        if (!$this->communityDiscussion->canModerateForum($request->user(), $forum)) {
            abort(403);
        }

        $this->communityDiscussion->pinReply($forum, $reply);

        return back()->with('success', 'Reply pinned.');
    }

    public function unpinReply(Request $request, CourseForum $forum)
    {
        if (!$this->communityDiscussion->canModerateForum($request->user(), $forum)) {
            abort(403);
        }

        $this->communityDiscussion->unpinReply($forum);

        return back()->with('success', 'Pinned reply removed.');
    }
}
