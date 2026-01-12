<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $professionalTypes = ProfessionalType::orderBy('sort_order')->get();

        return Inertia::render('dashboard/professional-types/index', [
            'professionalTypes' => $professionalTypes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:professional_types,name',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        ProfessionalType::create($validated);

        return redirect()->route('professional-types.index')->with('success', 'Professional type created successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $professionalType = ProfessionalType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:professional_types,name,' . $id,
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $professionalType->update($validated);

        return redirect()->route('professional-types.index')->with('success', 'Professional type updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $professionalType = ProfessionalType::findOrFail($id);
        
        // Check if any users are using this professional type
        if ($professionalType->users()->count() > 0) {
            return redirect()->route('professional-types.index')
                ->with('error', 'Cannot delete professional type. There are users associated with it.');
        }

        $professionalType->delete();

        return redirect()->route('professional-types.index')->with('success', 'Professional type deleted successfully');
    }
}
