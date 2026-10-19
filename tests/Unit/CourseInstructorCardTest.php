<?php

use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course\Course;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Course\CourseService;
use App\Support\CourseInstructorCard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();
});

it('saves the instructor card name and biography without clearing an existing photo', function () {
    createInstructorCardCourseTable();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'instructor_card_photo' => 'https://cdn.example.com/card.jpg',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => "First paragraph.\n\nSecond paragraph.",
        'instructor_card_photo' => null,
        'instructor_card_remove_photo' => false,
    ]);

    $fresh = Course::query()->findOrFail($course->id);

    expect($fresh->instructor_card_name)->toBe('Engr. Krieshell, Drywall Instructor')
        ->and($fresh->instructor_card_biography)->toBe("First paragraph.\n\nSecond paragraph.")
        ->and($fresh->getRawOriginal('instructor_card_photo'))->toBe('https://cdn.example.com/card.jpg');

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => '   ',
        'instructor_card_biography' => '  ',
        'instructor_card_photo' => null,
        'instructor_card_remove_photo' => false,
    ]);

    $cleared = Course::query()->findOrFail($course->id);

    expect($cleared->instructor_card_name)->toBeNull()
        ->and($cleared->instructor_card_biography)->toBeNull()
        ->and($cleared->getRawOriginal('instructor_card_photo'))->toBe('https://cdn.example.com/card.jpg');
});

it('stores an uploaded instructor card photo and keeps it on a later text-only save', function () {
    createInstructorCardCourseTable();
    createInstructorCardMediaTable();
    Storage::fake('public');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'A saved biography.',
        'instructor_card_photo' => instructorCardImage('instructor.png'),
        'instructor_card_remove_photo' => false,
    ]);

    $withPhoto = Course::query()->findOrFail($course->id);
    $storedPhoto = $withPhoto->getRawOriginal('instructor_card_photo');

    expect($storedPhoto)->not->toBeNull()
        ->and($withPhoto->instructor_card_photo)->not->toBeEmpty()
        ->and($withPhoto->getMedia('*', ['name' => 'instructor-card-photo']))->toHaveCount(1);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'Updated biography.',
        'instructor_card_photo' => null,
        'instructor_card_remove_photo' => false,
    ]);

    $kept = Course::query()->findOrFail($course->id);

    expect($kept->instructor_card_biography)->toBe('Updated biography.')
        ->and($kept->getRawOriginal('instructor_card_photo'))->toBe($storedPhoto)
        ->and($kept->getMedia('*', ['name' => 'instructor-card-photo']))->toHaveCount(1);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'Updated biography.',
        'instructor_card_photo' => instructorCardImage('replacement.jpg', 'image/jpeg'),
        'instructor_card_remove_photo' => false,
    ]);

    $replaced = Course::query()->findOrFail($course->id);

    expect($replaced->getRawOriginal('instructor_card_photo'))->not->toBe($storedPhoto)
        ->and($replaced->getMedia('*', ['name' => 'instructor-card-photo']))->toHaveCount(1);
});

it('clears only the custom photo so the card falls back to the account photo', function () {
    createInstructorCardCourseTable();
    createInstructorCardMediaTable();

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'Custom biography.',
        'instructor_card_photo' => 'https://cdn.example.com/custom.jpg',
    ]);

    app(CourseService::class)->updateCourse((string) $course->id, [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'Custom biography.',
        'instructor_card_photo' => null,
        'instructor_card_remove_photo' => true,
    ]);

    $fresh = Course::query()->findOrFail($course->id);
    $fresh->setRelation('instructor', instructorCardAccount());

    expect($fresh->instructor_card_photo)->toBeNull()
        ->and($fresh->instructor_card_name)->toBe('Engr. Krieshell, Drywall Instructor')
        ->and(CourseInstructorCard::present($fresh)['photo'])->toBe('https://cdn.example.com/account.jpg');
});

it('falls back to the instructor account when the card name and biography are blank', function () {
    $course = new Course([
        'instructor_card_name' => '   ',
        'instructor_card_biography' => '',
        'instructor_card_photo' => null,
    ]);
    $course->setRelation('instructor', instructorCardAccount());

    $card = CourseInstructorCard::present($course);

    expect($card['heading'])->toBe('KRIESHELL ANN F. DALWATAN, TRAINER')
        ->and($card['biography'])->toBe("Account paragraph one.\n\nAccount paragraph two.")
        ->and($card['photo'])->toBe('https://cdn.example.com/account.jpg');

    $course->instructor_card_name = 'Engr. Krieshell, Drywall Instructor';
    $course->instructor_card_biography = "Course paragraph.";
    $course->instructor_card_photo = 'https://cdn.example.com/custom.jpg';

    $custom = CourseInstructorCard::present($course);

    expect($custom['heading'])->toBe('ENGR. KRIESHELL, DRYWALL INSTRUCTOR')
        ->and($custom['biography'])->toBe('Course paragraph.')
        ->and($custom['photo'])->toBe('https://cdn.example.com/custom.jpg');
});

it('keeps the current photo when the instructor card form has no new file', function () {
    $request = UpdateCourseRequest::create('/courses/1', 'POST', [
        'tab' => 'instructor-card',
        'instructor_card_name' => '  Engr. Krieshell, Drywall Instructor  ',
        'instructor_card_biography' => "  First paragraph.\n\nSecond paragraph.  ",
        'instructor_card_remove_photo' => '0',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->validated())->toMatchArray([
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => "First paragraph.\n\nSecond paragraph.",
        'instructor_card_remove_photo' => false,
        'instructor_card_photo' => null,
    ]);
});

it('rejects an instructor card photo that is not a jpg png or webp', function () {
    $gif = UploadedFile::fake()->create('instructor.gif', 20, 'image/gif');
    $request = UpdateCourseRequest::create('/courses/1', 'POST', [
        'tab' => 'instructor-card',
        'instructor_card_name' => 'Engr. Krieshell, Drywall Instructor',
        'instructor_card_biography' => 'A biography.',
    ], [], ['instructor_card_photo' => $gif]);
    $request->setContainer(app())->setRedirector(app('redirect'));

    try {
        $request->validateResolved();
        $this->fail('A GIF should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instructor_card_photo')
            ->and($exception->errors()['instructor_card_photo'][0])->toBe('The instructor photo must be a JPG, PNG, or WebP image.');
    }
});

function instructorCardImage(string $name, string $mime = 'image/png'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIVFhUVFRUYFxUXGBYYFxUYHBcYGBYVHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGhAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAMABAMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAADBAIFAAEGBwf/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAH/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCfABj/2Q==');

    return UploadedFile::fake()->createWithContent($name, $mime === 'image/jpeg' ? $jpeg : $png);
}

function instructorCardAccount(): Instructor
{
    $instructor = new Instructor([
        'designation' => '',
        'biography' => "Account paragraph one.\n\nAccount paragraph two.",
    ]);
    $instructor->id = 7;
    $user = new User([
        'name' => 'Krieshell Ann F. Dalwatan',
        'photo' => 'https://cdn.example.com/account.jpg',
    ]);
    $instructor->setRelation('user', $user);

    return $instructor;
}

function createInstructorCardCourseTable(): void
{
    Schema::dropIfExists('courses');
    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->string('instructor_card_name')->nullable();
        $table->text('instructor_card_biography')->nullable();
        $table->text('instructor_card_photo')->nullable();
        $table->timestamps();
    });
}

function createInstructorCardMediaTable(): void
{
    Schema::dropIfExists('media');
    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model', 'ssu_media_morph_idx');
        $table->uuid()->nullable()->unique();
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
}
