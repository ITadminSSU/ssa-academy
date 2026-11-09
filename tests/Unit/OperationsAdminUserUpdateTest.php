<?php

use App\Http\Requests\UpdateUserRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    foreach (['course_enrollments', 'courses', 'settings', 'instructors', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->boolean('can_manage_platform_settings')->default(true);
        $table->string('user_type')->nullable();
        $table->integer('status')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->string('candidate_status')->nullable();
        $table->boolean('pef_tagged')->default(false);
        $table->timestamp('pef_tagged_at')->nullable();
        $table->timestamp('pef_access_ends_at')->nullable();
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->string('sub_type')->nullable();
        $table->json('fields')->nullable();
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    User::create([
        'name' => 'Primary Admin',
        'given_name' => 'Primary',
        'last_name' => 'Admin',
        'email' => 'primary@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'can_manage_platform_settings' => true,
        'status' => 1,
    ]);

    $this->operationsAdmin = User::create([
        'name' => 'Ops Admin',
        'given_name' => 'Ops',
        'last_name' => 'Admin',
        'email' => 'ops@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'can_manage_platform_settings' => false,
        'status' => 1,
    ]);

    $this->student = User::create([
        'name' => 'Martin Ong',
        'given_name' => 'Martin',
        'last_name' => 'Ong',
        'email' => 'martin.ong1998@gmail.com',
        'password' => Hash::make('password'),
        'role' => 'student',
        'user_type' => 'external',
        'status' => 1,
        'pef_tagged' => false,
    ]);
});

test('an operations admin can save a user and the PEF tag when the form also posts the current account type', function () {
    Setting::create([
        'type' => 'system',
        'fields' => [
            'pef_enabled' => true,
            'pef_course_ids' => [999001],
            'pef_access_duration' => '2 years',
        ],
    ]);

    $validated = validateUserUpdate($this->operationsAdmin, $this->student, [
        'last_name' => 'Updated',
        'account_type' => 'external',
        'pef_tagged' => true,
    ]);

    expect($validated)->not->toHaveKey('account_type')
        ->and($validated['pef_tagged'])->toBeTrue()
        ->and($validated['last_name'])->toBe('Updated');

    app(UserService::class)->updateUser($this->student->id, $validated);

    $student = $this->student->fresh();

    expect($student->last_name)->toBe('Updated')
        ->and($student->name)->toBe('Martin Updated')
        ->and($student->pef_tagged)->toBeTrue()
        ->and($student->pef_access_ends_at)->not->toBeNull()
        ->and($student->role)->toBe('student')
        ->and($student->user_type->value)->toBe('external');
});

test('an operations admin cannot change the account type and that rejection saves nothing', function () {
    $exception = null;

    try {
        validateUserUpdate($this->operationsAdmin, $this->student, [
            'last_name' => 'Escalated',
            'account_type' => 'admin',
            'pef_tagged' => true,
        ]);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['account_type'][0])->toBe('You do not have permission to change account type.');

    $student = $this->student->fresh();

    expect($student->last_name)->toBe('Ong')
        ->and($student->pef_tagged)->toBeFalse()
        ->and($student->role)->toBe('student');
});

test('checking PEF while the package is off rejects the save and leaves the account unchanged', function () {
    Setting::create([
        'type' => 'system',
        'fields' => [
            'pef_enabled' => false,
            'pef_course_ids' => [],
        ],
    ]);

    $validated = validateUserUpdate($this->operationsAdmin, $this->student, [
        'last_name' => 'Blocked',
        'account_type' => 'external',
        'pef_tagged' => true,
    ]);

    $exception = null;

    try {
        app(UserService::class)->updateUser($this->student->id, $validated);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['pef_tagged'][0])->toBe('Turn on the PEF package and choose its courses before tagging a student.')
        ->and($this->student->fresh()->last_name)->toBe('Ong')
        ->and($this->student->fresh()->pef_tagged)->toBeFalse();
});

function validateUserUpdate(User $actor, User $student, array $overrides): array
{
    Auth::login($actor);

    $payload = [];

    foreach (array_merge([
        'given_name' => 'Martin',
        'last_name' => 'Ong',
        'email' => $student->email,
        'status' => 1,
        'user_type' => 'external',
        'pef_tagged' => false,
        'designation' => '',
        'account_type' => 'external',
        'password' => '',
        'password_confirmation' => '',
    ], $overrides) as $key => $value) {
        $payload[$key] = $value === '' ? null : $value;
    }

    $request = UpdateUserRequest::create('/users/'.$student->id, 'PUT', $payload);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $actor);

    $route = new Route(['PUT'], '/users/{user}', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $request->validateResolved();

    return $request->validated();
}
