<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Services\Auth\SingleSessionService;
use App\Services\AuthService;
use App\Services\LearnerTypeResolver;
use App\Services\LegalAgreementService;
use Illuminate\Http\Request;
use Inertia\Response;

test('the signup page opens without a course', function () {
    $auth = Mockery::mock(AuthService::class);
    $auth->shouldReceive('googleAuthStatus')->once()->andReturn(['authStatus' => false]);
    $auth->shouldReceive('recaptchaStatus')->once()->andReturn([
        'status' => false,
        'siteKey' => '',
        'secretKey' => '',
    ]);

    $legal = Mockery::mock(LegalAgreementService::class);
    $legal->shouldReceive('documentPayload')->once()->andReturn([]);

    $controller = new RegisteredUserController(
        $auth,
        Mockery::mock(LearnerTypeResolver::class),
        $legal,
        Mockery::mock(SingleSessionService::class),
    );

    $response = $controller->create(Request::create('/register', 'GET'));

    expect($response)->toBeInstanceOf(Response::class);

    $props = (new ReflectionClass($response))->getProperty('props')->getValue($response);

    expect($props['sourceCourse'])->toBeNull();
});
