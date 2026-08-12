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
        $validated = $this->validatedMember($request, photoRequired: true);

        $this->teamMembers->create($validated, $request->file('photo'));

        return back()->with('success', 'Team member added successfully.');
    }

    public function update(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $validated = $this->validatedMember($request, photoRequired: false);

        $this->teamMembers->update($teamMember, $validated, $request->file('photo'));

        return back()->with('success', 'Team member updated successfully.');
    }

    private function validatedMember(Request $request, bool $photoRequired): array
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        if (! $request->hasFile('photo')) {
            $request->request->remove('photo');
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'photo' => ($photoRequired ? 'required' : 'nullable').'|image|mimes:jpeg,png,jpg|max:15360',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $this->teamMembers->delete($teamMember);

        return back()->with('success', 'Team member removed successfully.');
    }
}
