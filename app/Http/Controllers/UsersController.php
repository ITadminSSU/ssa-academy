<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportUsersRequest;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\AdminUserProvisioningService;
use App\Support\MasterAdmin;
use App\Services\UserService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UsersController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected AdminUserProvisioningService $adminUserProvisioning,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = $this->userService->getUsers([
            ...$request->all(),
            'paginate' => true,
            'include_all_roles' => true,
            'role_filter' => $request->string('role_filter')->toString() ?: 'all',
            'registered_from' => $request->string('registered_from')->toString() ?: null,
            'registered_to' => $request->string('registered_to')->toString() ?: null,
        ]);

        return Inertia::render('dashboard/users/index', [
            'users' => $users,
            'roleFilters' => $this->userService->roleFilterOptions(),
            'roleCounts' => $this->userService->getRoleCounts(),
            'filters' => [
                'role_filter' => $request->string('role_filter')->toString() ?: 'all',
                'search' => $request->string('search')->toString(),
                'registered_from' => $request->string('registered_from')->toString(),
                'registered_to' => $request->string('registered_to')->toString(),
            ],
            'protectedUserId' => MasterAdmin::userId(),
        ]);
    }

    public function export(ExportUsersRequest $request)
    {
        return $this->userService->exportUsersCsv($request->validated());
    }

    /**
     * View user's CV/Resume (inline viewing)
     */
    public function viewCv(string $id)
    {
        return $this->serveCv($id, download: false);
    }

    /**
     * Download user's CV/Resume
     */
    public function downloadCv(string $id)
    {
        return $this->serveCv($id, download: true);
    }

    private function serveCv(string $id, bool $download)
    {
        $user = User::findOrFail($id);
        $cvMedia = $user->getFirstMedia('cv_resume');

        if (! $cvMedia) {
            abort(404, 'CV/Resume not found');
        }

        $fileName = $cvMedia->file_name;
        $mimeType = $cvMedia->mime_type ?: 'application/octet-stream';
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$fileName.'"';

        if ($cvMedia->disk !== 's3') {
            $localPath = $cvMedia->getPath();
            if (is_string($localPath) && $localPath !== '' && file_exists($localPath)) {
                return $download
                    ? response()->download($localPath, $fileName, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => $disposition,
                    ])
                    : response()->file($localPath, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => $disposition,
                    ]);
            }
        }

        $fileUrl = media_public_url($cvMedia);

        if ($fileUrl === '') {
            abort(404, 'CV/Resume file not found');
        }

        return redirect()->away($fileUrl);
    }

    /**
     * Create a new admin, internal employee, or trainer account (admin-provisioned).
     */
    public function store(StoreAdminUserRequest $request)
    {
        $user = $this->adminUserProvisioning->provision($request->validated(), $request);

        $label = match (true) {
            $user->role === 'admin' && ! $user->canManagePlatformSettings() => 'Operations admin',
            $user->role === 'admin' => 'Admin',
            $user->role === 'instructor' => 'Trainer',
            default => 'Internal employee',
        };

        return redirect()->back()->with('success', "{$label} account created for {$user->email}.");
    }

    /**
     * Update the user's account.
     */
    public function update(UpdateUserRequest $request, string $user)
    {
        $this->userService->updateUser($user, $request->validated());

        $updated = User::findOrFail($user);

        $label = match (true) {
            $updated->role === 'admin' && ! $updated->canManagePlatformSettings() => 'Operations admin',
            $updated->role === 'admin' => 'Admin',
            $updated->role === 'instructor' => 'Trainer',
            default => 'User',
        };

        $message = $updated->isAccountActive()
            ? "{$label} account updated successfully."
            : "{$label} account disabled. They can no longer sign in.";

        return redirect()->back()->with('success', $message);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        if (isAdmin()) {
            try {
                $this->userService->deleteUser($id);

                return redirect()->back()->with('success', 'User account deleted successfully');
            } catch (\InvalidArgumentException $exception) {
                return redirect()->back()->with('error', $exception->getMessage());
            } catch (QueryException $exception) {
                return redirect()->back()->with(
                    'error',
                    'This user could not be deleted because they still have related course activity. Remove or reassign their enrollments and forum posts, then try again.'
                );
            }
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
