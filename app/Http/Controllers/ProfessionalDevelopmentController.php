<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalDevelopmentGuide;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfessionalDevelopmentController extends Controller
{
    public function index()
    {
        $guides = ProfessionalDevelopmentGuide::orderBy('sort')->get();

        return Inertia::render('dashboard/professional-development/index', [
            'guides' => $guides,
        ]);
    }

    public function update(Request $request, ProfessionalDevelopmentGuide $guide)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_published' => ['boolean'],
        ]);

        $guide->update($data);

        return redirect()->back()->with('success', 'Guide updated successfully');
    }
}
