<?php

use App\Services\Auth\EmailVerificationCodeService;

test('verification codes expire in 15 minutes', function () {
    expect(app(EmailVerificationCodeService::class)->expireMinutes())->toBe(15);
});
