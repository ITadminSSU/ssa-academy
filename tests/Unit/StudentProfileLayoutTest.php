<?php

use App\Http\Requests\StoreStudentProfileDocumentRequest;
use App\Http\Requests\UpdateStudentExperienceRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\Course\Course;
use App\Models\Course\CourseCertificate;
use App\Models\User;
use App\Services\Course\CourseCertificateIssuanceService;
use App\Services\StudentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    Schema::dropIfExists('media');
    Schema::dropIfExists('course_certificates');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('given_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->default('student');
        $table->unsignedTinyInteger('status')->default(1);
        $table->text('social_links')->nullable();
        $table->unsignedBigInteger('professional_type_id')->nullable();
        $table->string('professional_type_other')->nullable();
        $table->text('estimating_software')->nullable();
        $table->string('estimating_software_other')->nullable();
        $table->string('construction_experience')->nullable();
        $table->boolean('worked_as_construction_va')->nullable();
        $table->string('referred_by')->nullable();
        $table->boolean('referrer_is_employee')->nullable();
        $table->boolean('has_us_estimating_experience')->nullable();
        $table->timestamps();
    });

    Schema::create('course_certificates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('identifier')->nullable();
        $table->string('certificate_id')->nullable();
        $table->string('verification_reference')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->uuid('uuid')->nullable();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });

    Schema::enableForeignKeyConstraints();
});

function layoutStudent(): User
{
    return User::create([
        'name' => 'Raiza Limpiada',
        'given_name' => 'Raiza',
        'last_name' => 'Limpiada',
        'email' => 'student-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'role' => 'student',
        'status' => 1,
        'social_links' => [
            ['host' => 'website', 'profile_link' => 'https://example.com'],
            ['host' => 'facebook', 'profile_link' => 'https://facebook.com/old'],
            ['host' => 'twitter', 'profile_link' => 'https://x.com/old'],
            ['host' => 'linkedin', 'profile_link' => 'https://linkedin.com/in/old'],
        ],
        'professional_type_id' => 4,
        'estimating_software' => ['Excel'],
        'construction_experience' => '1–3 Years',
        'worked_as_construction_va' => false,
        'referred_by' => 'Alex',
        'referrer_is_employee' => true,
    ]);
}

test('saving the new profile keeps stored background answers and other social links', function () {
    $user = layoutStudent();

    $saved = app(StudentService::class)->updateProfile([
        'given_name' => 'Raiza',
        'last_name' => 'Limpiada',
        'facebook' => 'https://facebook.com/raiza',
        'has_us_estimating_experience' => false,
    ], (string) $user->id);

    $hosts = collect($saved->social_links)->pluck('profile_link', 'host');

    expect($saved->professional_type_id)->toBe(4)
        ->and($saved->estimating_software)->toBe(['Excel'])
        ->and($saved->construction_experience)->toBe('1–3 Years')
        ->and($saved->worked_as_construction_va)->toBeFalse()
        ->and($saved->referred_by)->toBe('Alex')
        ->and($saved->referrer_is_employee)->toBeTrue()
        ->and($saved->has_us_estimating_experience)->toBeFalse()
        ->and($hosts->get('facebook'))->toBe('https://facebook.com/raiza')
        ->and($hosts->get('website'))->toBe('https://example.com')
        ->and($hosts->get('twitter'))->toBe('https://x.com/old')
        ->and($hosts->get('linkedin'))->toBe('https://linkedin.com/in/old');
});

test('clearing facebook removes only the facebook link', function () {
    $user = layoutStudent();

    $saved = app(StudentService::class)->updateProfile([
        'facebook' => null,
        'has_us_estimating_experience' => true,
    ], (string) $user->id);

    $hosts = collect($saved->social_links)->pluck('host');

    expect($hosts->all())->not->toContain('facebook')
        ->and($hosts->all())->toContain('website', 'twitter', 'linkedin')
        ->and($saved->has_us_estimating_experience)->toBeTrue();
});

test('an assessment result does not release a certificate', function () {
    $user = layoutStudent();
    $saved = app(StudentService::class)->updateProfile([
        'criteria_assessment' => UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\n% assessment\n"),
    ], (string) $user->id);

    $course = new Course;
    $course->id = 9;
    $course->exists = true;
    $certificates = app(CourseCertificateIssuanceService::class);

    expect($saved->getMedia('criteria_assessment'))->toHaveCount(1)
        ->and($saved->getFirstMedia('criteria_assessment')?->file_name)->toBe('result.pdf')
        ->and($certificates->hasReleaseDocuments($saved))->toBeFalse()
        ->and($certificates->issueForCourse($course, $saved))->toBeNull()
        ->and($certificates->finishCourseNotice($saved, false))->toBe('Course completed successfully. Upload your CV/resume and government ID on your profile to release your certificate.')
        ->and(CourseCertificate::query()->count())->toBe(0);

    $replaced = app(StudentService::class)->updateProfile([
        'criteria_assessment' => UploadedFile::fake()->createWithContent('updated-result.pdf', "%PDF-1.4\n% replacement\n"),
    ], (string) $user->id);

    expect($replaced->getMedia('criteria_assessment'))->toHaveCount(1)
        ->and($replaced->getFirstMedia('criteria_assessment')?->file_name)->toBe('updated-result.pdf');
});

test('removing a saved cv holds the certificate until both files are back', function () {
    $user = layoutStudent();
    $saved = app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
        'id_document' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% id\n"),
    ], (string) $user->id);

    $certificates = app(CourseCertificateIssuanceService::class);

    expect($certificates->hasReleaseDocuments($saved))->toBeTrue();

    $cleared = app(StudentService::class)->updateProfile([
        'remove_cv' => true,
    ], (string) $user->id);

    expect($cleared->getMedia('cv_resume'))->toHaveCount(0)
        ->and($cleared->hasMedia('id_document'))->toBeTrue()
        ->and($certificates->hasReleaseDocuments($cleared))->toBeFalse();

    $replaced = app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('new-cv.pdf', "%PDF-1.4\n% new\n"),
        'remove_cv' => true,
    ], (string) $user->id);

    expect($replaced->getFirstMedia('cv_resume')?->file_name)->toBe('new-cv.pdf')
        ->and($certificates->hasReleaseDocuments($replaced))->toBeTrue();
});

test('removing an assessment result leaves the cv and government id in place', function () {
    $user = layoutStudent();
    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
        'criteria_assessment' => UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\n% assessment\n"),
    ], (string) $user->id);

    $cleared = app(StudentService::class)->updateProfile([
        'remove_criteria_assessment' => true,
    ], (string) $user->id);

    expect($cleared->getMedia('criteria_assessment'))->toHaveCount(0)
        ->and($cleared->hasMedia('cv_resume'))->toBeTrue();
});

test('the profile form no longer requires the background questions', function () {
    $request = UpdateStudentProfileRequest::create('/student/profile', 'POST', [
        'given_name' => 'Raiza',
        'last_name' => 'Limpiada',
        'facebook' => '',
        'has_us_estimating_experience' => '0',
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())->not->toThrow(ValidationException::class)
        ->and($request->rules())->not->toHaveKey('has_us_estimating_experience')
        ->and($request->rules())->not->toHaveKey('cv_resume')
        ->and($request->rules())->not->toHaveKey('id_document')
        ->and($request->rules())->not->toHaveKey('criteria_assessment');

    $invalid = UpdateStudentProfileRequest::create('/student/profile', 'POST', [
        'given_name' => 'Raiza',
        'facebook' => 'not a url',
    ]);
    $invalid->setContainer(app());
    $invalid->setRedirector(app('redirect'));

    expect(fn () => $invalid->validateResolved())->toThrow(ValidationException::class);
});

test('a student can view their own saved documents', function () {
    $this->withoutMiddleware();
    $user = layoutStudent();

    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
        'id_document' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% id\n"),
        'criteria_assessment' => UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\n% assessment\n"),
    ], (string) $user->id);

    $viewer = $user->fresh();

    foreach (['cv' => 'cv.pdf', 'id' => 'id.pdf', 'assessment' => 'result.pdf'] as $type => $name) {
        $response = $this->actingAs($viewer)->get(route('student.profile.document', ['type' => $type]));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))->toContain($name);
    }
});

test('view does not open another student document', function () {
    $this->withoutMiddleware();
    $owner = layoutStudent();

    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
    ], (string) $owner->id);

    $other = layoutStudent();

    $this->actingAs($other)->get(route('student.profile.document', ['type' => 'cv']))->assertNotFound();
});

test('viewing a document stored on cloudflare r2 redirects to a temporary link', function () {
    $this->withoutMiddleware();
    config([
        'filesystems.disks.s3.bucket' => 'ssa-academy-files',
        'filesystems.disks.s3.endpoint' => 'https://example.r2.cloudflarestorage.com',
        'filesystems.disks.s3.region' => 'auto',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
        'filesystems.disks.s3.use_path_style_endpoint' => false,
    ]);

    $user = layoutStudent();
    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
    ], (string) $user->id);

    $media = $user->fresh()->getFirstMedia('cv_resume');
    $media->disk = 's3';
    $media->save();

    $response = $this->actingAs($user->fresh())->get(route('student.profile.document', ['type' => 'cv']));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))->not->toBe('');
});

function documentRequest(string $type, array $files = []): StoreStudentProfileDocumentRequest
{
    $request = StoreStudentProfileDocumentRequest::create('/student/profile/documents/'.$type, 'POST', [], [], $files);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(function () use ($request, $type) {
        $route = new Route('POST', 'student/profile/documents/{type}', []);
        $route->bind($request);
        $route->setParameter('type', $type);

        return $route;
    });

    return $request;
}

test('profile documents may be larger than 10 MB and up to 5 GB', function () {
    $request = documentRequest('cv', [
        'cv_resume' => UploadedFile::fake()->create('cv.pdf', 11 * 1024, 'application/pdf'),
    ]);

    expect(fn () => $request->validateResolved())->not->toThrow(ValidationException::class)
        ->and($request->rules()['cv_resume'])->toContain('max:'.UpdateStudentProfileRequest::PROFILE_DOCUMENT_MAX_KILOBYTES)
        ->and(UpdateStudentProfileRequest::PROFILE_DOCUMENT_MAX_KILOBYTES)->toBe(5 * 1024 * 1024);
});

test('save changes leaves the experience answer and uploaded documents in place', function () {
    $this->withoutMiddleware();
    $user = layoutStudent();
    $user->forceFill(['has_us_estimating_experience' => true])->save();

    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
    ], (string) $user->id);

    $this->actingAs($user->fresh())->post(route('student.profile.update'), [
        'given_name' => 'Alexis',
        'last_name' => 'Pacapac',
        'facebook' => 'https://facebook.com/alexis',
        'has_us_estimating_experience' => '0',
        'remove_cv' => '1',
        'cv_resume' => UploadedFile::fake()->createWithContent('other.pdf', "%PDF-1.4\n% other\n"),
    ])->assertRedirect();

    $saved = $user->fresh();
    $facebook = collect($saved->social_links)->firstWhere('host', 'facebook');

    expect($saved->given_name)->toBe('Alexis')
        ->and($saved->last_name)->toBe('Pacapac')
        ->and($saved->has_us_estimating_experience)->toBeTrue()
        ->and($saved->getFirstMedia('cv_resume')?->file_name)->toBe('cv.pdf')
        ->and($facebook['profile_link'] ?? null)->toBe('https://facebook.com/alexis');
});

test('confirming the experience answer saves only that answer', function () {
    $this->withoutMiddleware();
    $user = layoutStudent();
    $user->forceFill([
        'given_name' => 'Raiza',
        'has_us_estimating_experience' => false,
    ])->save();

    $savedYes = $this->actingAs($user->fresh())->post(
        route('student.profile.experience'),
        ['has_us_estimating_experience' => '1'],
        ['Accept' => 'application/json'],
    );

    $savedYes->assertOk();
    expect($savedYes->json('has_us_estimating_experience'))->toBeTrue()
        ->and($user->fresh()->has_us_estimating_experience)->toBeTrue()
        ->and($user->fresh()->given_name)->toBe('Raiza');

    $savedNo = $this->actingAs($user->fresh())->post(
        route('student.profile.experience'),
        ['has_us_estimating_experience' => '0'],
        ['Accept' => 'application/json'],
    );

    $savedNo->assertOk();
    expect($savedNo->json('has_us_estimating_experience'))->toBeFalse()
        ->and($user->fresh()->has_us_estimating_experience)->toBeFalse()
        ->and($user->fresh()->given_name)->toBe('Raiza');
});

test('the experience answer requires a yes or no', function () {
    $request = UpdateStudentExperienceRequest::create('/student/profile/experience', 'POST', []);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);

    $yes = UpdateStudentExperienceRequest::create('/student/profile/experience', 'POST', [
        'has_us_estimating_experience' => '1',
    ]);
    $yes->setContainer(app());
    $yes->setRedirector(app('redirect'));
    $yes->validateResolved();

    expect($yes->boolean('has_us_estimating_experience'))->toBeTrue();
});

test('a chosen document saves immediately and replaces only that file', function () {
    $this->withoutMiddleware();
    $user = layoutStudent();

    app(StudentService::class)->updateProfile([
        'id_document' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% id\n"),
    ], (string) $user->id);

    $stored = $this->actingAs($user->fresh())->post(
        route('student.profile.document.store', ['type' => 'cv']),
        ['cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n")],
        ['Accept' => 'application/json'],
    );

    $stored->assertOk();
    expect($stored->json('document.file_name'))->toBe('cv.pdf')
        ->and($stored->json('document.view_url'))->toContain('/student/profile/documents/cv');

    $replaced = $this->actingAs($user->fresh())->post(
        route('student.profile.document.store', ['type' => 'cv']),
        ['cv_resume' => UploadedFile::fake()->createWithContent('new-cv.pdf', "%PDF-1.4\n% new\n")],
        ['Accept' => 'application/json'],
    );

    $replaced->assertOk();
    $saved = $user->fresh();

    expect($replaced->json('document.file_name'))->toBe('new-cv.pdf')
        ->and($saved->getMedia('cv_resume'))->toHaveCount(1)
        ->and($saved->getFirstMedia('id_document')?->file_name)->toBe('id.pdf');
});

test('removing a document deletes only that file', function () {
    $this->withoutMiddleware();
    $owner = layoutStudent();
    $other = layoutStudent();

    app(StudentService::class)->updateProfile([
        'cv_resume' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n% cv\n"),
        'criteria_assessment' => UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\n% assessment\n"),
    ], (string) $owner->id);

    $this->actingAs($other)->delete(
        route('student.profile.document.destroy', ['type' => 'cv']),
        [],
        ['Accept' => 'application/json'],
    )->assertOk();

    expect($owner->fresh()->getFirstMedia('cv_resume')?->file_name)->toBe('cv.pdf');

    $this->actingAs($owner->fresh())->delete(
        route('student.profile.document.destroy', ['type' => 'assessment']),
        [],
        ['Accept' => 'application/json'],
    )->assertOk();

    $saved = $owner->fresh();

    expect($saved->getMedia('criteria_assessment'))->toHaveCount(0)
        ->and($saved->getFirstMedia('cv_resume')?->file_name)->toBe('cv.pdf');
});
