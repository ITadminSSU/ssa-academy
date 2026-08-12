<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Services\TeamMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:15360',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->teamMembers->create($validated, $request->file('photo'));

        return back()->with('success', 'Team member added successfully.');
    }

    public function update(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:15360',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->teamMembers->update($teamMember, $validated, $request->file('photo'));

        return back()->with('success', 'Team member updated successfully.');
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $this->teamMembers->delete($teamMember);

        return back()->with('success', 'Team member removed successfully.');
    }
}
