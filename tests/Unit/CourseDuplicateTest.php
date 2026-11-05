<?php

use App\Models\ChunkedUpload;
use App\Models\Course\Course;
use App\Models\Course\CourseAssignment;
use App\Models\Course\CourseFaq;
use App\Models\Course\CourseLandingPage;
use App\Models\Course\CourseLiveClass;
use App\Models\Course\CourseOutcome;
use App\Models\Course\CourseRequirement;
use App\Models\Course\CourseSection;
use App\Models\Course\QuizQuestion;
use App\Models\Course\SectionLesson;
use App\Models\Course\SectionQuiz;
use App\Models\Course\UsExperiencePlan;
use App\Services\BunnyStreamService;
use App\Services\Course\CourseDuplicateException;
use App\Services\Course\CourseDuplicateService;
use App\Services\Course\CourseSectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('public');
    Schema::dropAllTables();
    Schema::enableForeignKeyConstraints();

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('course_type')->default('general');
        $table->string('status');
        $table->string('video_copy_status', 32)->nullable();
        $table->text('video_copy_error')->nullable();
        $table->string('level')->nullable();
        $table->text('short_description')->nullable();
        $table->text('description')->nullable();
        $table->string('hero_headline')->nullable();
        $table->string('meta_title')->nullable();
        $table->string('language')->nullable();
        $table->string('pricing_type')->default('paid');
        $table->string('billing_model')->nullable();
        $table->string('audience')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->boolean('discount')->default(false);
        $table->decimal('discount_price', 10, 2)->nullable();
        $table->decimal('subscription_price', 10, 2)->nullable();
        $table->decimal('installment_price', 10, 2)->nullable();
        $table->unsignedSmallInteger('installment_months')->nullable();
        $table->string('stripe_product_id')->nullable();
        $table->string('stripe_price_id')->nullable();
        $table->string('stripe_ongoing_price_id')->nullable();
        $table->boolean('drip_content')->default(false);
        $table->string('expiry_duration')->nullable();
        $table->string('thumbnail')->nullable();
        $table->string('banner')->nullable();
        $table->string('preview')->nullable();
        $table->text('hero_photo')->nullable();
        $table->text('hero_video')->nullable();
        $table->text('instructor_card_photo')->nullable();
        $table->text('review_carousel')->nullable();
        $table->string('expiry_type')->default('lifetime');
        $table->unsignedBigInteger('final_exam_id')->nullable();
        $table->unsignedBigInteger('instructor_id');
        $table->unsignedBigInteger('course_category_id');
        $table->timestamps();
    });

    Schema::create('course_sections', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('sort')->default(0);
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('section_lessons', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('sort')->default(0);
        $table->boolean('status')->default(true);
        $table->string('lesson_type')->default('video');
        $table->text('lesson_src')->nullable();
        $table->string('lesson_provider')->nullable();
        $table->text('embed_source')->nullable();
        $table->text('thumbnail')->nullable();
        $table->string('duration')->nullable();
        $table->boolean('is_free')->default(false);
        $table->text('description')->nullable();
        $table->text('summary')->nullable();
        $table->unsignedInteger('lesson_number')->nullable();
        $table->string('bunny_video_id')->nullable();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('course_lesson_video_copies', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->foreignId('section_lesson_id')->constrained('section_lessons')->cascadeOnDelete();
        $table->string('source_bunny_video_id');
        $table->string('status')->default('pending');
        $table->text('error')->nullable();
        $table->timestamps();
    });

    Schema::create('lesson_resources', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('type')->default('document');
        $table->text('resource')->nullable();
        $table->boolean('is_downloadable')->default(true);
        $table->foreignId('section_lesson_id')->constrained('section_lessons')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('section_quizzes', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('sort')->default(0);
        $table->string('duration')->default('00:10:00');
        $table->integer('hours')->default(0);
        $table->integer('minutes')->default(10);
        $table->integer('seconds')->default(0);
        $table->integer('total_mark')->default(1);
        $table->integer('pass_mark')->default(1);
        $table->integer('retake')->default(1);
        $table->integer('drip_rule')->nullable();
        $table->text('summary')->nullable();
        $table->integer('num_questions')->nullable();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('quiz_questions', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->string('type')->default('single');
        $table->json('options')->nullable();
        $table->json('answer')->nullable();
        $table->integer('sort')->default(0);
        $table->foreignId('section_quiz_id')->constrained('section_quizzes')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('course_assignments', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('total_mark')->default(100);
        $table->integer('pass_mark')->default(50);
        $table->integer('retake')->default(1);
        $table->text('summary')->nullable();
        $table->string('sample_project_path')->nullable();
        $table->dateTime('deadline')->nullable();
        $table->boolean('late_submission')->default(false);
        $table->integer('late_total_mark')->default(0);
        $table->dateTime('late_deadline')->nullable();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('course_faqs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('question');
        $table->text('answer');
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('course_requirements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('requirement');
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('course_outcomes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('outcome');
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('course_live_classes', function (Blueprint $table) {
        $table->id();
        $table->string('provider')->nullable();
        $table->string('class_topic');
        $table->dateTime('class_date_and_time')->nullable();
        $table->text('class_note')->nullable();
        $table->json('additional_info')->nullable();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('course_landing_pages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->unique()->constrained('courses')->cascadeOnDelete();
        $table->timestamp('countdown_ends_at')->nullable();
        $table->text('hero_photo')->nullable();
        $table->string('video_url', 2048)->nullable();
        $table->string('video_duration', 20)->nullable();
        $table->string('coach_line')->nullable();
        $table->longText('embed_html')->nullable();
        $table->json('reviews')->nullable();
        $table->timestamps();
    });

    Schema::create('us_experience_plans', function (Blueprint $table) {
        $table->id();
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('group_name');
        $table->text('group_description')->nullable();
        $table->string('title');
        $table->unsignedInteger('sort_order')->default(0);
        $table->unsignedInteger('pass_mark')->default(85);
        $table->unsignedInteger('max_attempts')->default(10);
        $table->boolean('published')->default(false);
        $table->json('drawings')->nullable();
        $table->string('blank_template_file_url')->nullable();
        $table->string('blank_template_file_name')->nullable();
        $table->string('answer_key_file_url')->nullable();
        $table->string('answer_key_file_name')->nullable();
        $table->json('line_items')->nullable();
        $table->timestamp('parsed_at')->nullable();
        $table->string('tutorial_video_url')->nullable();
        $table->string('tutorial_video_name')->nullable();
        $table->timestamps();
    });

    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('enrollment_type')->default('paid');
        $table->timestamps();
    });

    Schema::create('course_certificates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->timestamps();
    });

    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->string('stripe_subscription_id');
        $table->timestamps();
    });

    Schema::create('chunked_uploads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('filename')->nullable();
        $table->string('original_filename')->nullable();
        $table->string('file_path')->nullable();
        $table->text('file_url')->nullable();
        $table->string('disk')->nullable();
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size')->nullable();
        $table->string('upload_id')->nullable();
        $table->string('key')->nullable();
        $table->string('status')->nullable();
        $table->integer('chunks_completed')->default(0);
        $table->integer('total_chunks')->default(0);
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->uuid('uuid')->nullable();
        $table->string('collection_name')->nullable();
        $table->string('name')->nullable();
        $table->string('file_name')->nullable();
        $table->string('mime_type')->nullable();
        $table->string('disk')->nullable();
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size')->default(0);
        $table->json('manipulations')->nullable();
        $table->json('custom_properties')->nullable();
        $table->json('generated_conversions')->nullable();
        $table->json('responsive_images')->nullable();
        $table->unsignedInteger('order_column')->nullable();
        $table->timestamps();
    });

    Schema::create('course_progress', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->integer('completed_lessons')->default(0);
        $table->timestamps();
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->decimal('amount', 10, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('us_experience_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
        $table->unsignedBigInteger('us_experience_plan_id');
        $table->timestamps();
    });

    Schema::create('watch_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('course_id')->nullable();
        $table->string('current_section_id')->nullable();
        $table->string('current_watching_id')->nullable();
        $table->string('current_watching_type')->nullable();
        $table->string('next_watching_id')->nullable();
        $table->string('next_watching_type')->nullable();
        $table->string('prev_watching_id')->nullable();
        $table->string('prev_watching_type')->nullable();
        $table->json('completed_watching')->nullable();
        $table->json('lesson_watch_progress')->nullable();
        $table->timestamp('completion_date')->nullable();
        $table->timestamps();
    });
});

function duplicateFixtureCourse(): Course
{
    Storage::disk('public')->put('courses/cover.jpg', 'cover-bytes');
    Storage::disk('public')->put('lessons/handout.pdf', 'handout-bytes');
    Storage::disk('public')->put('quizzes/plan.pdf', 'plan-bytes');

    $course = Course::query()->create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating',
        'course_type' => 'general',
        'status' => 'approved',
        'level' => 'beginner',
        'short_description' => 'Learn drywall estimating',
        'description' => 'Full description',
        'hero_headline' => 'Estimate with confidence',
        'meta_title' => 'Drywall Estimating Course',
        'language' => 'en',
        'pricing_type' => 'paid',
        'billing_model' => 'upfront_subscription',
        'audience' => 'public',
        'price' => 75,
        'subscription_price' => 6,
        'drip_content' => true,
        'expiry_type' => 'limited',
        'expiry_duration' => '6 months',
        'stripe_product_id' => 'prod_source',
        'stripe_price_id' => 'price_source',
        'stripe_ongoing_price_id' => null,
        'final_exam_id' => 55,
        'instructor_id' => 7,
        'course_category_id' => 3,
    ]);

    DB::table('courses')->where('id', $course->id)->update([
        'thumbnail' => 'courses/cover.jpg',
    ]);

    $section = CourseSection::query()->create([
        'title' => 'Week 1',
        'sort' => 1,
        'course_id' => $course->id,
    ]);

    $lesson = SectionLesson::query()->create([
        'title' => 'Introduction',
        'sort' => 1,
        'status' => true,
        'lesson_type' => 'video',
        'duration' => '00:05:00',
        'bunny_video_id' => 'source-vid',
        'course_id' => $course->id,
        'course_section_id' => $section->id,
    ]);

    $lesson->resources()->create([
        'title' => 'Handout',
        'type' => 'document',
        'resource' => 'lessons/handout.pdf',
        'is_downloadable' => true,
    ]);

    $quiz = SectionQuiz::query()->create([
        'title' => 'Check',
        'sort' => 2,
        'duration' => '00:10:00',
        'total_mark' => 1,
        'pass_mark' => 1,
        'course_id' => $course->id,
        'course_section_id' => $section->id,
    ]);

    QuizQuestion::query()->create([
        'title' => 'Which plan is this?',
        'type' => 'single',
        'options' => json_encode([
            ['label' => 'A', 'file_url' => 'quizzes/plan.pdf'],
        ]),
        'answer' => json_encode(['A']),
        'sort' => 1,
        'section_quiz_id' => $quiz->id,
    ]);

    CourseAssignment::query()->create([
        'title' => 'Practice takeoff',
        'total_mark' => 100,
        'pass_mark' => 70,
        'summary' => 'Submit a takeoff',
        'deadline' => now()->addWeek(),
        'course_id' => $course->id,
    ]);

    CourseFaq::query()->create([
        'course_id' => $course->id,
        'question' => 'How long is it?',
        'answer' => 'Six weeks',
        'sort' => 1,
    ]);

    CourseRequirement::query()->create([
        'course_id' => $course->id,
        'requirement' => 'A computer',
        'sort' => 1,
    ]);

    CourseOutcome::query()->create([
        'course_id' => $course->id,
        'outcome' => 'Read a plan',
        'sort' => 1,
    ]);

    CourseLiveClass::query()->create([
        'provider' => 'zoom',
        'class_topic' => 'Office hours',
        'class_date_and_time' => now()->addWeek(),
        'course_id' => $course->id,
    ]);

    CourseLandingPage::query()->create([
        'course_id' => $course->id,
        'hero_photo' => 'courses/cover.jpg',
        'coach_line' => 'Start here',
        'reviews' => [
            ['name' => 'Ada', 'image' => 'courses/cover.jpg', 'quote' => 'Clear lessons'],
        ],
    ]);

    $plan = UsExperiencePlan::query()->create([
        'course_id' => $course->id,
        'group_name' => 'Takeoffs',
        'title' => 'Plan 1',
        'sort_order' => 1,
        'blank_template_file_url' => 'quizzes/plan.pdf',
        'blank_template_file_name' => 'plan.pdf',
        'drawings' => [
            ['file_url' => 'quizzes/plan.pdf', 'file_name' => 'plan.pdf'],
        ],
        'line_items' => [
            ['item' => 'Drywall', 'unit' => 'sheet', 'expected_qty' => 10],
        ],
    ]);

    DB::table('course_enrollments')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'enrollment_type' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subscriptions')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_source',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('course_progress')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'completed_lessons' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('payment_histories')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'amount' => 75,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('course_certificates')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('watch_histories')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'current_watching_id' => (string) $lesson->id,
        'current_watching_type' => 'lesson',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('us_experience_attempts')->insert([
        'user_id' => 9,
        'course_id' => $course->id,
        'us_experience_plan_id' => $plan->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $course;
}

it('copies a course onto a new draft and leaves the original course untouched', function () {
    $source = duplicateFixtureCourse();
    $sourceLessonId = SectionLesson::query()->where('course_id', $source->id)->value('id');
    $sourceQuizId = SectionQuiz::query()->where('course_id', $source->id)->value('id');

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('isEnabled')->andReturn(true);
    $bunny->shouldReceive('copyVideo')->once()->with('source-vid', 'Copy of Drywall Estimating — Introduction')->andReturn('copied-vid');
    $bunny->shouldReceive('deleteVideo')->never();

    $copy = app(CourseDuplicateService::class)->duplicate($source, 'Copy of Drywall Estimating');

    $source->refresh();

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->title)->toBe('Copy of Drywall Estimating')
        ->and($copy->slug)->toBe('copy-of-drywall-estimating')
        ->and($copy->status)->toBe('draft')
        ->and($copy->billing_model->value)->toBe('upfront_subscription')
        ->and($copy->price)->toEqual(75)
        ->and((bool) $copy->drip_content)->toBeTrue()
        ->and($copy->expiry_type)->toBe('limited')
        ->and($copy->expiry_duration)->toBe('6 months')
        ->and($copy->hero_headline)->toBe('Estimate with confidence')
        ->and($copy->meta_title)->toBe('Drywall Estimating Course')
        ->and($copy->description)->toBe('Full description')
        ->and($copy->final_exam_id)->toBe(55)
        ->and($copy->stripe_product_id)->toBeNull()
        ->and($copy->stripe_price_id)->toBeNull()
        ->and($copy->stripe_ongoing_price_id)->toBeNull()
        ->and($copy->getRawOriginal('thumbnail'))->not->toBe('courses/cover.jpg')
        ->and($source->title)->toBe('Drywall Estimating')
        ->and($source->slug)->toBe('drywall-estimating')
        ->and($source->status)->toBe('approved')
        ->and($source->billing_model->value)->toBe('upfront_subscription')
        ->and($source->stripe_product_id)->toBe('prod_source')
        ->and($source->stripe_price_id)->toBe('price_source')
        ->and(SectionLesson::query()->where('course_id', $source->id)->value('id'))->toBe($sourceLessonId)
        ->and(SectionLesson::query()->where('course_id', $source->id)->value('bunny_video_id'))->toBe('source-vid')
        ->and(SectionQuiz::query()->where('course_id', $source->id)->value('id'))->toBe($sourceQuizId);

    $copiedLesson = SectionLesson::query()->where('course_id', $copy->id)->first();
    $copiedQuestion = QuizQuestion::query()->whereHas('lesson_quiz', fn ($query) => $query->where('course_id', $copy->id))->first();
    $options = json_decode((string) $copiedQuestion->getRawOriginal('options'), true);

    expect($copiedLesson->id)->not->toBe($sourceLessonId)
        ->and($copiedLesson->bunny_video_id)->toBe('copied-vid')
        ->and($copiedLesson->resources()->value('resource'))->not->toBe('lessons/handout.pdf')
        ->and($copiedQuestion->title)->toBe('Which plan is this?')
        ->and($options[0]['file_url'])->not->toBe('quizzes/plan.pdf')
        ->and(CourseFaq::query()->where('course_id', $copy->id)->value('question'))->toBe('How long is it?')
        ->and(CourseAssignment::query()->where('course_id', $copy->id)->count())->toBe(1)
        ->and(DB::table('course_enrollments')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('course_enrollments')->where('course_id', $source->id)->count())->toBe(1)
        ->and(DB::table('subscriptions')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('subscriptions')->where('course_id', $source->id)->count())->toBe(1)
        ->and(DB::table('course_certificates')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('course_certificates')->where('course_id', $source->id)->count())->toBe(1)
        ->and(DB::table('course_progress')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('course_progress')->where('course_id', $source->id)->count())->toBe(1)
        ->and(DB::table('payment_histories')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('payment_histories')->where('course_id', $source->id)->count())->toBe(1)
        ->and(DB::table('watch_histories')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('us_experience_attempts')->where('course_id', $copy->id)->count())->toBe(0)
        ->and(DB::table('us_experience_attempts')->where('course_id', $source->id)->count())->toBe(1)
        ->and(CourseRequirement::query()->where('course_id', $copy->id)->value('requirement'))->toBe('A computer')
        ->and(CourseOutcome::query()->where('course_id', $copy->id)->value('outcome'))->toBe('Read a plan')
        ->and(CourseLiveClass::query()->where('course_id', $copy->id)->value('class_topic'))->toBe('Office hours')
        ->and(Storage::disk('public')->exists('courses/cover.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('lessons/handout.pdf'))->toBeTrue()
        ->and(Storage::disk('public')->exists('quizzes/plan.pdf'))->toBeTrue();

    $copiedLanding = CourseLandingPage::query()->where('course_id', $copy->id)->first();
    $copiedPlan = UsExperiencePlan::query()->where('course_id', $copy->id)->first();
    $thumbKey = ltrim(str_replace('/storage/', '', (string) $copy->getRawOriginal('thumbnail')), '/');

    expect($copiedLanding->coach_line)->toBe('Start here')
        ->and($copiedLanding->hero_photo)->not->toBe('courses/cover.jpg')
        ->and($copiedLanding->reviews[0]['image'])->not->toBe('courses/cover.jpg')
        ->and($copiedPlan->title)->toBe('Plan 1')
        ->and($copiedPlan->line_items[0]['item'])->toBe('Drywall')
        ->and($copiedPlan->blank_template_file_url)->not->toBe('quizzes/plan.pdf')
        ->and($copiedPlan->drawings[0]['file_url'])->not->toBe('quizzes/plan.pdf')
        ->and($copiedPlan->id)->not->toBe(UsExperiencePlan::query()->where('course_id', $source->id)->value('id'))
        ->and(Storage::disk('public')->exists($thumbKey))->toBeTrue()
        ->and(Storage::disk('public')->get($thumbKey))->toBe('cover-bytes');

    $copyId = $copy->id;
    $copy->delete();

    expect(Course::query()->find($source->id))->not->toBeNull()
        ->and(Course::query()->find($copyId))->toBeNull()
        ->and(SectionLesson::query()->where('course_id', $source->id)->count())->toBe(1)
        ->and(SectionLesson::query()->where('bunny_video_id', 'source-vid')->count())->toBe(1)
        ->and(SectionLesson::query()->where('course_id', $copyId)->count())->toBe(0);
});

it('saves the draft before any lesson video is copied', function () {
    $source = duplicateFixtureCourse();

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('copyVideo')->never();
    $bunny->shouldReceive('deleteVideo')->never();

    $copy = app(CourseDuplicateService::class)->createDraft($source, 'Copy of Drywall Estimating');

    expect($copy->status)->toBe('draft')
        ->and($copy->video_copy_status)->toBe('copying')
        ->and(SectionLesson::query()->where('course_id', $copy->id)->value('bunny_video_id'))->toBeNull()
        ->and(SectionLesson::query()->where('course_id', $source->id)->value('bunny_video_id'))->toBe('source-vid')
        ->and(DB::table('course_lesson_video_copies')->where('course_id', $copy->id)->where('status', 'pending')->count())->toBe(1)
        ->and(DB::table('course_enrollments')->where('course_id', $copy->id)->count())->toBe(0)
        ->and($source->fresh()->stripe_product_id)->toBe('prod_source');
});

it('keeps the new draft when a lesson video cannot be copied', function () {
    $source = duplicateFixtureCourse();
    SectionLesson::query()->create([
        'title' => 'Second video',
        'sort' => 2,
        'status' => true,
        'lesson_type' => 'video',
        'bunny_video_id' => 'source-vid-2',
        'course_id' => $source->id,
        'course_section_id' => CourseSection::query()->where('course_id', $source->id)->value('id'),
    ]);

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('isEnabled')->andReturn(true);
    $bunny->shouldReceive('copyVideo')->once()->ordered()->andReturn('new-vid-1');
    $bunny->shouldReceive('copyVideo')->once()->ordered()->andThrow(new RuntimeException('Bunny unavailable'));
    $bunny->shouldReceive('deleteVideo')->never();

    $copy = app(CourseDuplicateService::class)->duplicate($source, 'Copy of Drywall Estimating');

    expect(Course::query()->count())->toBe(2)
        ->and($copy->video_copy_status)->toBe('failed')
        ->and($copy->video_copy_error)->toContain('Second video')
        ->and($copy->video_copy_error)->toContain('original course was not changed')
        ->and(SectionLesson::query()->where('course_id', $source->id)->where('bunny_video_id', 'source-vid')->count())->toBe(1)
        ->and(SectionLesson::query()->where('course_id', $source->id)->where('bunny_video_id', 'source-vid-2')->count())->toBe(1)
        ->and(SectionLesson::query()->where('course_id', $copy->id)->where('title', 'Introduction')->value('bunny_video_id'))->toBe('new-vid-1')
        ->and(SectionLesson::query()->where('course_id', $copy->id)->where('title', 'Second video')->value('bunny_video_id'))->toBeNull()
        ->and($source->fresh()->title)->toBe('Drywall Estimating')
        ->and($source->fresh()->stripe_price_id)->toBe('price_source')
        ->and(Storage::disk('public')->exists('courses/cover.jpg'))->toBeTrue();
});

it('rolls back copied files when the new course cannot be saved', function () {
    $source = duplicateFixtureCourse();
    DB::table('courses')->where('id', $source->id)->update([
        'banner' => 'courses/missing-banner.jpg',
    ]);

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('copyVideo')->never();
    $bunny->shouldReceive('deleteVideo')->never();

    expect(fn () => app(CourseDuplicateService::class)->duplicate($source->fresh(), 'Copy of Drywall Estimating'))
        ->toThrow(CourseDuplicateException::class);

    $source->refresh();

    expect(Course::query()->count())->toBe(1)
        ->and($source->title)->toBe('Drywall Estimating')
        ->and($source->stripe_price_id)->toBe('price_source')
        ->and(SectionLesson::query()->where('bunny_video_id', 'source-vid')->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing([
            'courses/cover.jpg',
            'lessons/handout.pdf',
            'quizzes/plan.pdf',
        ]);
});

it('does not delete a bunny video that another lesson still uses', function () {
    $source = duplicateFixtureCourse();
    $sectionId = CourseSection::query()->where('course_id', $source->id)->value('id');
    $shared = SectionLesson::query()->create([
        'title' => 'Shared video',
        'sort' => 2,
        'status' => true,
        'lesson_type' => 'video',
        'bunny_video_id' => 'source-vid',
        'course_id' => $source->id,
        'course_section_id' => $sectionId,
    ]);
    $original = SectionLesson::query()->where('course_id', $source->id)->where('title', 'Introduction')->first();

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('deleteVideo')->never();

    app(CourseSectionService::class)->deleteSectionLesson((string) $original->id);

    expect(SectionLesson::query()->find($shared->id))->not->toBeNull()
        ->and(SectionLesson::query()->find($shared->id)->bunny_video_id)->toBe('source-vid')
        ->and(SectionLesson::query()->find($original->id))->toBeNull();
});

it('deletes a bunny video only after the last lesson stops using it', function () {
    $source = duplicateFixtureCourse();
    $lesson = SectionLesson::query()->where('course_id', $source->id)->first();

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('deleteVideo')->once()->with('source-vid')->andReturn(true);

    app(CourseSectionService::class)->deleteSectionLesson((string) $lesson->id);

    expect(SectionLesson::query()->find($lesson->id))->toBeNull();
});

it('keeps a shared uploaded file when one lesson that uses it is deleted', function () {
    $source = duplicateFixtureCourse();
    $sectionId = CourseSection::query()->where('course_id', $source->id)->value('id');
    Storage::disk('public')->put('course-copies/shared/file.pdf', 'shared-bytes');
    $upload = ChunkedUpload::query()->create([
        'filename' => 'file.pdf',
        'original_filename' => 'file.pdf',
        'file_path' => 'course-copies/shared/file.pdf',
        'file_url' => '/storage/course-copies/shared/file.pdf',
        'disk' => 'public',
        'key' => 'course-copies/shared/file.pdf',
        'status' => 'completed',
        'chunks_completed' => 1,
        'total_chunks' => 1,
    ]);
    $first = SectionLesson::query()->create([
        'title' => 'File A',
        'sort' => 3,
        'lesson_type' => 'document',
        'lesson_src' => $upload->file_url,
        'course_id' => $source->id,
        'course_section_id' => $sectionId,
    ]);
    $second = SectionLesson::query()->create([
        'title' => 'File B',
        'sort' => 4,
        'lesson_type' => 'document',
        'lesson_src' => $upload->file_url,
        'course_id' => $source->id,
        'course_section_id' => $sectionId,
    ]);

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('deleteVideo')->never();

    app(CourseSectionService::class)->deleteSectionLesson((string) $first->id);

    expect(SectionLesson::query()->find($second->id))->not->toBeNull()
        ->and(ChunkedUpload::query()->find($upload->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists('course-copies/shared/file.pdf'))->toBeTrue();
});

it('does not delete the source bunny video when a copied lesson is deleted', function () {
    $source = duplicateFixtureCourse();

    $bunny = $this->mock(BunnyStreamService::class);
    $bunny->shouldReceive('isEnabled')->andReturn(true);
    $bunny->shouldReceive('copyVideo')->once()->andReturn('copied-vid');
    $bunny->shouldReceive('deleteVideo')->once()->with('copied-vid')->andReturn(true);
    $bunny->shouldNotReceive('deleteVideo')->with('source-vid');

    $copy = app(CourseDuplicateService::class)->duplicate($source, 'Copy of Drywall Estimating');
    $copiedLesson = SectionLesson::query()->where('course_id', $copy->id)->first();

    app(CourseSectionService::class)->deleteSectionLesson((string) $copiedLesson->id);

    expect(SectionLesson::query()->where('course_id', $source->id)->value('bunny_video_id'))->toBe('source-vid')
        ->and(SectionLesson::query()->find($copiedLesson->id))->toBeNull()
        ->and(Course::query()->find($source->id))->not->toBeNull();
});

it('copies a bunny video onto a new id', function () {
    config([
        'bunny.enabled' => true,
        'bunny.library_id' => '99',
        'bunny.api_key' => 'test-key',
        'bunny.cdn_hostname' => 'vz-test.b-cdn.net',
        'bunny.token_auth_key' => '',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if ($request->method() === 'GET' && str_contains($url, '/videos/source-vid')) {
            return Http::response(['guid' => 'source-vid', 'length' => 4], 200);
        }

        if ($request->method() === 'POST' && str_ends_with(rtrim($url, '/'), '/videos')) {
            return Http::response(['guid' => 'new-vid'], 200);
        }

        if ($request->method() === 'GET' && str_contains($url, 'b-cdn.net')) {
            return Http::response('video-bytes', 200, ['Content-Type' => 'video/mp4']);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/videos/new-vid')) {
            return Http::response(['success' => true], 200);
        }

        if ($request->method() === 'DELETE' && str_contains($url, '/videos/new-vid')) {
            return Http::response('', 200);
        }

        return Http::response('unexpected', 500);
    });

    $newId = app(BunnyStreamService::class)->copyVideo('source-vid', 'Copied introduction');

    expect($newId)->toBe('new-vid');
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/videos/new-vid'));
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('deletes only the new bunny video when the upload fails', function () {
    config([
        'bunny.enabled' => true,
        'bunny.library_id' => '99',
        'bunny.api_key' => 'test-key',
        'bunny.cdn_hostname' => 'vz-test.b-cdn.net',
        'bunny.token_auth_key' => '',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if ($request->method() === 'GET' && str_contains($url, '/videos/source-vid')) {
            return Http::response(['guid' => 'source-vid', 'length' => 4], 200);
        }

        if ($request->method() === 'POST' && str_ends_with(rtrim($url, '/'), '/videos')) {
            return Http::response(['guid' => 'new-vid'], 200);
        }

        if ($request->method() === 'GET' && str_contains($url, 'b-cdn.net')) {
            return Http::response('video-bytes', 200, ['Content-Type' => 'video/mp4']);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/videos/new-vid')) {
            return Http::response(['success' => false], 500);
        }

        if ($request->method() === 'DELETE' && str_contains($url, '/videos/new-vid')) {
            return Http::response('', 200);
        }

        return Http::response('unexpected', 500);
    });

    expect(fn () => app(BunnyStreamService::class)->copyVideo('source-vid', 'Copied introduction'))
        ->toThrow(RuntimeException::class);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/videos/new-vid'));
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/videos/source-vid'));
});
