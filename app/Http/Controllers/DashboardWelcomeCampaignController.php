<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDashboardWelcomeCampaignRequest;
use App\Models\DashboardWelcomeCampaign;
use App\Services\DashboardWelcomeCampaignService;
use Illuminate\Http\RedirectResponse;

class DashboardWelcomeCampaignController extends Controller
{
    public function __construct(
        private DashboardWelcomeCampaignService $campaigns,
    ) {}

    public function store(StoreDashboardWelcomeCampaignRequest $request): RedirectResponse
    {
        $this->campaigns->create($request->validated());

        return back()->with('success', 'Welcome campaign created.');
    }

    public function update(StoreDashboardWelcomeCampaignRequest $request, DashboardWelcomeCampaign $campaign): RedirectResponse
    {
        $data = $request->validated();

        // Allow keeping existing file video without re-upload.
        if (
            ($data['video_type'] ?? '') === 'file'
            && empty($data['video_url'])
            && empty($data['new_video'])
            && empty($data['clear_video'])
            && filled($campaign->video_url)
        ) {
            $data['video_url'] = $campaign->video_url;
        }

        if (
            empty($data['poster_url'])
            && empty($data['new_poster'])
            && empty($data['clear_poster'])
            && filled($campaign->poster_url)
        ) {
            $data['poster_url'] = $campaign->poster_url;
        }

        $this->campaigns->update($campaign, $data);

        return back()->with('success', 'Welcome campaign updated.');
    }

    public function destroy(DashboardWelcomeCampaign $campaign): RedirectResponse
    {
        $this->campaigns->delete($campaign);

        return back()->with('success', 'Welcome campaign deleted.');
    }
}
