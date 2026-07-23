<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
        ]);

        return Inertia::render('dashboard/users/index', [
            'users' => $users,
            'roleFilters' => $this->userService->roleFilterOptions(),
            'roleCounts' => $this->userService->getRoleCounts(),
            'filters' => [
                'role_filter' => $request->string('role_filter')->toString() ?: 'all',
                'search' => $request->string('search')->toString(),
            ],
            'protectedUserId' => MasterAdmin::userId(),
        ]);
    }

    /**
     * View user's CV/Resume (inline viewing)
     */
    public function viewCv(string $id)
    {
        $user = User::findOrFail($id);
        $cvMedia = $user->getFirstMedia('cv_resume');

        if (!$cvMedia) {
            abort(404, 'CV/Resume not found');
        }

        try {
            $filePath = $cvMedia->getPath();
            $fileName = $cvMedia->file_name;
            $mimeType = $cvMedia->mime_type;

            // Check if file exists on local disk
            if (file_exists($filePath)) {
                return response()->file(
                    $filePath,
                    [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                    ]
                );
            }

            // For S3 or remote storage, get the URL
            $fileUrl = $cvMedia->getFullUrl();
            if ($fileUrl) {
                return redirect($fileUrl);
            }

            abort(404, 'CV/Resume file not found');
        } catch (\Exception $e) {
            // Fallback: try to get URL from media
            $fileUrl = $cvMedia->getFullUrl();
            if ($fileUrl) {
                return redirect($fileUrl);
            }
            
            abort(404, 'CV/Resume not accessible: ' . $e->getMessage());
        }
    }

    /**
     * Create a new admin, internal employee, or trainer account (admin-provisioned).
     */
    public function store(StoreAdminUserRequest $request)
    {
        $user = $this->adminUserProvisioning->provision($request->validated(), $request);

        $label = match ($user->role) {
            'admin' => 'Admin',
            'instructor' => 'Trainer',
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

        $label = match ($updated->role) {
            'admin' => 'Admin',
            'instructor' => 'Trainer',
            default => 'User',
        };

        return redirect()->back()->with('success', "{$label} account updated successfully.");
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

    /**
     * Download user's CV/Resume
     */
    public function downloadCv(string $id)
    {
        $user = User::findOrFail($id);
        $cvMedia = $user->getFirstMedia('cv_resume');

        if (!$cvMedia) {
            abort(404, 'CV/Resume not found');
        }

        try {
            $filePath = $cvMedia->getPath();
            $fileName = $cvMedia->file_name;
            $mimeType = $cvMedia->mime_type;

            // Check if file exists on local disk
            if (file_exists($filePath)) {
                return response()->download(
                    $filePath,
                    $fileName,
                    [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    ]
                );
            }

            // For S3 or remote storage, get the URL
            $fileUrl = $cvMedia->getFullUrl();
            if ($fileUrl) {
                return redirect($fileUrl);
            }

            abort(404, 'CV/Resume file not found');
        } catch (\Exception $e) {
            // Fallback: try to get URL from media
            $fileUrl = $cvMedia->getFullUrl();
            if ($fileUrl) {
                return redirect($fileUrl);
            }
            
            abort(404, 'CV/Resume not accessible: ' . $e->getMessage());
        }
    }
}
