<?php

use App\Models\Course\UsExperiencePlan;
use App\Services\UsExperience\UsExperiencePlanService;

it('clears only the walkthrough video fields on a US Experience plan', function () {
    $plan = Mockery::mock(UsExperiencePlan::class);
    $plan->shouldReceive('update')
        ->once()
        ->with([
            'tutorial_video_url' => null,
            'tutorial_video_name' => null,
        ])
        ->andReturn(true);
    $plan->shouldReceive('fresh')->once()->andReturn($plan);

    $cleared = app(UsExperiencePlanService::class)->clearTutorialVideo($plan);

    expect($cleared)->toBe($plan);
});
