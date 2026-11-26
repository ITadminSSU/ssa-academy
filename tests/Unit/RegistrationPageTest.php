<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Services\Auth\SingleSessionService;
use App\Services\AuthService;
use App\Services\LearnerTypeResolver;
use App\Services\LegalAgreementService;
use Illuminate\Http\Request;
use Inertia\Response;

test('the signup page opens without a course', function () {
    $props = registrationPageProps('/register');

    expect($props['sourceCourse'])->toBeNull()
        ->and($props['registrationSource'])->toBeNull();
});

test('an evt link exposes the evt source', function () {
    $props = registrationPageProps('/register?source=evt');

    expect($props['sourceCourse'])->toBeNull()
        ->and($props['registrationSource'])->toBe('evt');
});

test('another source exposes no source', function () {
    $props = registrationPageProps('/register?source=other');

    expect($props['sourceCourse'])->toBeNull()
        ->and($props['registrationSource'])->toBeNull();
});

test('a course id of evt exposes no source', function () {
    $props = registrationPageProps('/register?source_course_id=evt');

    expect($props['sourceCourse'])->toBeNull()
        ->and($props['registrationSource'])->toBeNull();
});

function registrationPageProps(string $url): array
{
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

    $response = $controller->create(Request::create($url, 'GET'));

    expect($response)->toBeInstanceOf(Response::class);

    return (new ReflectionClass($response))->getProperty('props')->getValue($response);
}
