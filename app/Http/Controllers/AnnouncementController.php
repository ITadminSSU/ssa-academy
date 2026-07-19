<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $announcementService) {}

    public function index()
    {
        return Inertia::render('dashboard/announcements/index', [
            'announcements' => $this->announcementService->forManagement(Auth::user())->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        $announcement = Announcement::create([
            ...$data,
            'user_id' => Auth::id(),
        ]);

        if ($this->announcementService->shouldNotifyOnCreate($data)) {
            $this->announcementService->notifyLearners($announcement, Auth::user(), Auth::id());
        }

        return redirect()->back()->with('success', 'Announcement published');
    }

    public function update(Request $request, Announcement $announcement)
    {
        if (!$this->announcementService->authorCanManage($request->user(), $announcement)) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        $shouldNotify = $this->announcementService->shouldNotifyOnUpdate($announcement, $data);

        $announcement->update($data);

        if ($shouldNotify) {
            $this->announcementService->notifyLearners($announcement->fresh(), Auth::user(), Auth::id());
        }

        return redirect()->back()->with('success', 'Announcement updated');
    }

    public function destroy(Announcement $announcement)
    {
        if (!$this->announcementService->authorCanManage(Auth::user(), $announcement)) {
            abort(403);
        }

        $announcement->delete();

        return redirect()->back()->with('success', 'Announcement deleted');
    }
}
