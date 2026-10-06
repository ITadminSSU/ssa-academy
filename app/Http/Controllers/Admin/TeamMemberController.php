<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamMemberRequest;
use App\Models\TeamMember;
use App\Services\TeamMemberService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function __construct(private TeamMemberService $teamMembers) {}

    public function index(): Response
    {
        return Inertia::render('dashboard/settings/team/index', [
            'teamMembers' => $this->teamMembers->listForAdmin(),
        ]);
    }

    public function store(TeamMemberRequest $request): RedirectResponse
    {
        $this->teamMembers->create($request->validated(), $request->file('photo'));

        return back()->with('success', 'Team member added successfully.');
    }

    public function update(TeamMemberRequest $request, TeamMember $teamMember): RedirectResponse
    {
        $this->teamMembers->update($teamMember, $request->validated(), $request->file('photo'));

        return back()->with('success', 'Team member updated successfully.');
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $this->teamMembers->delete($teamMember);

        return back()->with('success', 'Team member removed successfully.');
    }
}
