<?php

use App\Support\SiteAlert;

it('hides the alert when the toggle is off', function () {
    expect(SiteAlert::visibleMessage([
        'site_alert_enabled' => false,
        'site_alert_message' => 'Server upgrade in progress',
    ]))->toBeNull();
});

it('shows the custom message when the toggle is on', function () {
    expect(SiteAlert::visibleMessage([
        'site_alert_enabled' => '1',
        'site_alert_message' => '  We will be down for 15 minutes.  ',
    ]))->toBe('We will be down for 15 minutes.');
});

it('falls back to the default message when enabled with a blank body', function () {
    expect(SiteAlert::visibleMessage([
        'site_alert_enabled' => true,
        'site_alert_message' => '   ',
    ]))->toBe(SiteAlert::DEFAULT_MESSAGE);
});

it('strips html and truncates long messages', function () {
    $alert = SiteAlert::fromFields([
        'site_alert_enabled' => true,
        'site_alert_message' => '<script>alert(1)</script>'.str_repeat('a', 400),
    ]);

    expect($alert['enabled'])->toBeTrue()
        ->and($alert['message'])->not->toContain('<script>')
        ->and(mb_strlen($alert['message']))->toBe(SiteAlert::MAX_LENGTH);
});
