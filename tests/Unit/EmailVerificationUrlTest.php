<?php

use App\Support\EmailVerificationUrl;

test('verification links stay valid for 24 hours', function () {
    expect(EmailVerificationUrl::expireMinutes())->toBe(1440);
});
