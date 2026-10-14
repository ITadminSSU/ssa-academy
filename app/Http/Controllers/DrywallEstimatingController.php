<?php

namespace App\Http\Controllers;

use App\Support\DrywallLanding;
use Inertia\Inertia;
use Inertia\Response;

class DrywallEstimatingController extends Controller
{
    public function __construct(private DrywallLanding $landing) {}

    public function show(): Response
    {
        $page = $this->landing->publicPage();

        return Inertia::render('drywall-estimating/index', [
            'landing' => $page['landing'],
            'course' => $page['course'],
            'canViewCurriculum' => $page['canViewCurriculum'],
            'enrollUrl' => $page['enrollUrl'],
        ])->withViewData([
            'metaTitle' => 'Drywall Estimating',
            'metaDescription' => 'A practical, step-by-step Drywall Estimating course for beginners and aspiring Virtual Assistants who want real-world U.S. estimating experience.',
            'ogTitle' => 'Master Drywall Estimating and Get Real-World U.S. Experience',
            'ogDescription' => 'A practical, step-by-step course designed for beginners and aspiring Virtual Assistants.',
        ]);
    }

    public function thanks(): Response
    {
        return Inertia::render('drywall-estimating/thank-you', [
            'enrollUrl' => $this->landing->publicPage()['enrollUrl'],
        ])->withViewData([
            'metaTitle' => 'Check your email',
            'metaDescription' => 'We sent the Drywall Estimating course details.',
        ]);
    }
}
