<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class PlatformToolsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/settings/platform-tools');
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');
        Artisan::call('up');

        return back()->with('success', 'Platform cache cleared successfully.');
    }

    public function storageLink(): RedirectResponse
    {
        Artisan::call('storage:link');

        return back()->with('success', 'Storage symlink created successfully.');
    }
}
