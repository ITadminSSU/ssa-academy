<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseCouponLabelRequest;
use App\Http\Requests\UpdateCourseCouponLabelRequest;
use App\Models\Course\CourseCouponLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseCouponLabelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/courses/coupons/labels', [
            'labels' => CourseCouponLabel::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreCourseCouponLabelRequest $request): RedirectResponse
    {
        CourseCouponLabel::query()->create($request->validated());

        return back()->with('success', 'Coupon label created.');
    }

    public function update(UpdateCourseCouponLabelRequest $request, CourseCouponLabel $couponLabel): RedirectResponse
    {
        $couponLabel->update($request->validated());

        return back()->with('success', 'Coupon label updated.');
    }

    public function destroy(CourseCouponLabel $couponLabel): RedirectResponse
    {
        $couponLabel->delete();

        return back()->with('success', 'Coupon label deleted. Coupons that used it are unchanged except the category is cleared.');
    }
}
