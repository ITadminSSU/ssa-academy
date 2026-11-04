<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class EvtLandingController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('evt/index')->withViewData([
            'metaTitle' => 'Become a Remote Construction Estimator',
            'metaDescription' => 'Exclusive Virtual Training (EVT) from SmartSourcing USA Academy. Specialized, practical training in Roofing, Siding & Drywall from Dec 1, 2026 – Apr 1, 2027.',
            'ogTitle' => 'Become a Remote Construction Estimator',
            'ogDescription' => 'Introducing the Exclusive Virtual Training (EVT). A free webinar on November 21.',
        ]);
    }
}
