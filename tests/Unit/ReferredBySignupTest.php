<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->unsignedTinyInteger('status')->nullable();
        $table->string('candidate_status')->nullable();
        $table->string('referred_by')->nullable();
        $table->boolean('referrer_is_employee')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    config(['account.single_session.enabled' => false]);
});

afterEach(function () {
    Auth::logout();
    Schema::dropIfExists('users');
});

test('a signup with a referrer and yes stores both values', function () {
    registerStudent([
        'email' => 'aireen@example.com',
        'referred_by' => 'Jordan',
        'referrer_is_employee' => true,
    ]);

    $user = User::query()->where('email', 'aireen@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->referred_by)->toBe('Jordan')
        ->and($user->referrer_is_employee)->toBeTrue();
});

test('a signup with the referrer blank stores no referrer', function () {
    registerStudent([
        'email' => 'home@example.com',
        'referred_by' => '   ',
        'referrer_is_employee' => true,
    ]);

    $user = User::query()->where('email', 'home@example.com')->first();

    expect($user->referred_by)->toBeNull()
        ->and($user->referrer_is_employee)->toBeNull();
});

test('a signup name without the employee answer is rejected', function () {
    expect(fn () => registerStudent([
        'email' => 'missing@example.com',
        'referred_by' => 'Jordan',
    ]))->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'missing@example.com')->exists())->toBeFalse();
});

test('a profile save with a name and yes accepts both values', function () {
    $request = profileRequest([
        'referred_by' => 'Jordan',
        'referrer_is_employee' => '1',
    ]);

    $request->validateResolved();

    expect($request->validated())->toMatchArray([
        'referred_by' => 'Jordan',
        'referrer_is_employee' => '1',
    ]);
});

test('a blank profile referrer stays valid and drops a leftover employee answer', function () {
    $request = profileRequest([
        'referred_by' => '   ',
        'referrer_is_employee' => '1',
    ]);

    $request->validateResolved();

    expect($request->validated())->toMatchArray([
        'referred_by' => null,
        'referrer_is_employee' => null,
    ]);
});

test('a profile name without the employee answer is rejected', function () {
    $request = profileRequest([
        'referred_by' => 'Jordan',
        'referrer_is_employee' => '',
    ]);

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

function profileRequest(array $overrides = []): UpdateStudentProfileRequest
{
    $request = UpdateStudentProfileRequest::create('/student/profile/update', 'POST', array_merge([
        'given_name' => 'Raiza',
        'last_name' => 'Limpiada',
        'referred_by' => '',
        'referrer_is_employee' => '',
    ], $overrides));
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    return $request;
}

function registerStudent(array $overrides = []): void
{
    $request = Request::create('/register', 'POST', array_merge([
        'given_name' => 'Aireen',
        'last_name' => 'Abasolo',
        'email' => 'student@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'recaptcha_status' => false,
        'accept_terms' => 'yes',
        'accept_legal_age' => 'yes',
        'referred_by' => '',
    ], $overrides));
    $request->setLaravelSession(app('session.store'));

    app(RegisteredUserController::class)->store($request);
}
