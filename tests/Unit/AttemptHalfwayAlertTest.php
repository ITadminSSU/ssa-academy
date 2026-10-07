<?php

use App\Models\Course\Course;
use App\Models\Course\CourseCategory;
use App\Models\Course\CourseSection;
use App\Models\Course\QuizQuestion;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Models\Instructor;
use App\Models\User;
use App\Notifications\AttemptHalfwayAlertNotification;
use App\Services\AttemptHalfwayAlertService;
use App\Services\Course\QuizTakeoffService;
use App\Services\Course\SectionQuizService;
use App\Services\UsExperience\UsExperienceAttemptService;
use App\Services\UsExperience\UsExperienceFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamQuestion;
use Modules\Exam\Models\ExamQuestionOption;
use Modules\Exam\Services\ExamAttemptService;
use Modules\Exam\Services\QuantityTakeoffGradingService;
use Modules\Exam\Services\QuantityTakeoffTemplateGenerator;
use Modules\Exam\Services\QuantityTakeoffXlsxParser;

beforeEach(function () {
    Cache::flush();
    createHalfwayAlertSchema();
});

function createHalfwayAlertSchema(): void
{
    Schema::disableForeignKeyConstraints();

    $tables = [
        'users',
        'instructors',
        'course_categories',
        'courses',
        'course_sections',
        'section_quizzes',
        'quiz_questions',
        'quiz_submissions',
        'question_answers',
        'notifications',
        'exams',
        'exam_questions',
        'exam_question_options',
        'exam_attempts',
        'exam_attempt_answers',
        'us_experience_plans',
        'us_experience_attempts',
        'jobs',
        'media',
    ];

    foreach ($tables as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role')->default('student');
        $table->string('password');
        $table->string('email')->unique();
        $table->integer('status')->nullable();
        $table->string('user_type')->nullable();
        $table->string('candidate_status')->nullable();
        $table->text('candidate_notes')->nullable();
        $table->timestamp('candidate_status_updated_at')->nullable();
        $table->boolean('can_manage_platform_settings')->default(false);
        $table->timestamp('email_verified_at')->nullable();
        $table->unsignedBigInteger('instructor_id')->nullable();
        $table->timestamp('legal_agreement_accepted_at')->nullable();
        $table->string('legal_agreement_version')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('instructors', function (Blueprint $table) {
        $table->id();
        $table->json('skills')->nullable();
        $table->text('biography')->nullable();
        $table->string('resume')->nullable();
        $table->string('designation')->nullable();
        $table->string('status')->default('pending');
        $table->json('payout_methods')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
    });

    Schema::create('course_categories', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->integer('sort')->default(0);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->string('course_type');
        $table->string('status');
        $table->string('level');
        $table->text('short_description');
        $table->unsignedBigInteger('instructor_id');
        $table->unsignedBigInteger('course_category_id');
        $table->timestamps();
    });

    Schema::create('course_sections', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('sort');
        $table->unsignedBigInteger('course_id');
        $table->timestamps();
    });

    Schema::create('section_quizzes', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('duration')->nullable();
        $table->integer('hours')->default(0);
        $table->integer('minutes')->default(0);
        $table->integer('seconds')->default(0);
        $table->integer('total_mark');
        $table->integer('pass_mark');
        $table->integer('retake')->default(1);
        $table->integer('sort')->nullable();
        $table->unsignedBigInteger('course_id');
        $table->unsignedBigInteger('course_section_id');
        $table->timestamps();
    });

    Schema::create('quiz_questions', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->string('type');
        $table->json('options')->nullable();
        $table->json('answer')->nullable();
        $table->integer('sort');
        $table->unsignedBigInteger('section_quiz_id');
        $table->timestamps();
    });

    Schema::create('quiz_submissions', function (Blueprint $table) {
        $table->id();
        $table->integer('attempts');
        $table->integer('correct_answers');
        $table->integer('incorrect_answers');
        $table->integer('total_marks');
        $table->boolean('is_passed');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('section_quiz_id');
        $table->timestamps();
    });

    Schema::create('question_answers', function (Blueprint $table) {
        $table->id();
        $table->json('answers');
        $table->boolean('is_correct');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('quiz_question_id');
        $table->timestamps();
    });

    Schema::create('notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::create('exams', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->nullable();
        $table->string('status')->default('draft');
        $table->decimal('pass_mark', 5, 2)->default(0);
        $table->decimal('total_marks', 10, 2)->default(0);
        $table->integer('max_attempts')->default(1);
        $table->unsignedBigInteger('instructor_id');
        $table->string('exam_mode')->default('standard');
        $table->json('takeoff_config')->nullable();
        $table->timestamps();
    });

    Schema::create('exam_questions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('exam_id');
        $table->string('question_type');
        $table->text('title');
        $table->decimal('marks', 5, 2)->default(1);
        $table->integer('sort')->default(0);
        $table->json('options')->nullable();
        $table->timestamps();
    });

    Schema::create('exam_question_options', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('exam_question_id');
        $table->text('option_text');
        $table->boolean('is_correct')->default(false);
        $table->integer('sort')->default(0);
        $table->timestamps();
    });

    Schema::create('exam_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('exam_id');
        $table->integer('attempt_number')->default(1);
        $table->timestamp('start_time')->nullable();
        $table->timestamp('end_time')->nullable();
        $table->decimal('total_marks', 10, 2)->default(0);
        $table->decimal('obtained_marks', 10, 2)->default(0);
        $table->decimal('percentage', 8, 2)->nullable();
        $table->integer('correct_answers')->default(0);
        $table->integer('incorrect_answers')->default(0);
        $table->boolean('is_passed')->default(false);
        $table->string('status')->default('in_progress');
        $table->string('tracking_reference')->nullable();
        $table->timestamps();
    });

    Schema::create('exam_attempt_answers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('exam_attempt_id');
        $table->unsignedBigInteger('exam_question_id');
        $table->json('answer_data')->nullable();
        $table->boolean('is_correct')->nullable();
        $table->decimal('marks_obtained', 8, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('us_experience_plans', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id');
        $table->string('group_name');
        $table->string('title');
        $table->unsignedInteger('sort_order')->default(0);
        $table->unsignedInteger('pass_mark')->default(85);
        $table->unsignedInteger('max_attempts')->default(10);
        $table->boolean('published')->default(false);
        $table->json('line_items')->nullable();
        $table->timestamps();
    });

    Schema::create('us_experience_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('us_experience_plan_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedInteger('attempt_number');
        $table->string('takeoff_pdf_url');
        $table->string('takeoff_pdf_name')->nullable();
        $table->string('boq_xlsx_url');
        $table->string('boq_xlsx_name')->nullable();
        $table->json('answer_data')->nullable();
        $table->decimal('marks_obtained', 8, 2)->nullable();
        $table->unsignedInteger('lines_correct')->nullable();
        $table->unsignedInteger('lines_total')->nullable();
        $table->decimal('lines_percent', 8, 2)->nullable();
        $table->json('grading_breakdown')->nullable();
        $table->string('status');
        $table->timestamp('submitted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model');
        $table->uuid('uuid')->nullable()->unique();
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
        $table->unsignedInteger('order_column')->nullable();
        $table->nullableTimestamps();
    });

    Schema::enableForeignKeyConstraints();
}

function halfwayLegalUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'status' => 1,
        'legal_agreement_accepted_at' => now(),
        'legal_agreement_version' => config('legal.agreement_version', '2026-07-16'),
    ], $overrides));
}

/**
 * @return array{instructorUser: User, instructor: Instructor, student: User, course: Course, section: CourseSection}
 */
function halfwayCourseGraph(): array
{
    $instructorUser = halfwayLegalUser([
        'role' => 'instructor',
        'name' => 'Pat Trainer',
    ]);

    $instructor = Instructor::create([
        'user_id' => $instructorUser->id,
        'skills' => ['estimating'],
        'biography' => 'Lead trainer',
        'resume' => 'resume.pdf',
        'designation' => 'Instructor',
        'status' => 'approved',
    ]);

    $instructorUser->update(['instructor_id' => $instructor->id]);
    $instructorUser->refresh();

    $category = CourseCategory::create([
        'title' => 'Estimating',
        'slug' => 'estimating-'.uniqid(),
        'sort' => 1,
        'status' => true,
    ]);

    $course = Course::create([
        'title' => 'Drywall Estimating',
        'slug' => 'drywall-estimating-'.uniqid(),
        'course_type' => 'general',
        'status' => 'approved',
        'level' => 'beginner',
        'short_description' => 'Short',
        'instructor_id' => $instructor->id,
        'course_category_id' => $category->id,
    ]);

    $section = CourseSection::create([
        'title' => 'Section 1',
        'sort' => 1,
        'course_id' => $course->id,
    ]);

    $student = halfwayLegalUser([
        'role' => 'student',
        'name' => 'Jordan Learner',
        'email' => 'jordan.learner.'.uniqid().'@example.com',
    ]);

    return compact('instructorUser', 'instructor', 'student', 'course', 'section');
}

function halfwayQuiz(Course $course, CourseSection $section, int $retake = 10, int $passMark = 80): SectionQuiz
{
    return SectionQuiz::create([
        'title' => 'Quantity Quiz',
        'duration' => '00:10:00',
        'hours' => 0,
        'minutes' => 10,
        'seconds' => 0,
        'total_mark' => 100,
        'pass_mark' => $passMark,
        'retake' => $retake,
        'sort' => 1,
        'course_id' => $course->id,
        'course_section_id' => $section->id,
    ]);
}

function halfwayKnowledgeQuestion(SectionQuiz $quiz): QuizQuestion
{
    return QuizQuestion::create([
        'title' => 'What is 2+2?',
        'type' => 'single',
        'options' => json_encode(['4', '5']),
        'answer' => json_encode(['4']),
        'sort' => 1,
        'section_quiz_id' => $quiz->id,
    ]);
}

function submitKnowledgeQuiz(SectionQuiz $quiz, User $student, QuizQuestion $question, bool $correct): QuizSubmission|false
{
    return app(SectionQuizService::class)->quizSubmission([
        'section_quiz_id' => $quiz->id,
        'user_id' => $student->id,
        'answers' => [
            [
                'question_id' => $question->id,
                'answer' => $correct ? ['4'] : ['5'],
            ],
        ],
    ]);
}

function assertHalfwaySentToInstructor(User $instructor, User $student, int $times = 1): void
{
    Notification::assertSentToTimes($instructor, AttemptHalfwayAlertNotification::class, $times);
    Notification::assertSentTo($instructor, AttemptHalfwayAlertNotification::class, function (AttemptHalfwayAlertNotification $notification, array $channels) use ($instructor, $student) {
        expect($channels)->toContain('mail')
            ->and($channels)->toContain('database');

        $mail = $notification->toMail($instructor);
        $data = $notification->toArray($instructor);

        expect($data)->toHaveKeys(['title', 'body', 'url', 'dedup_key'])
            ->and($data['title'])->toContain($student->name)
            ->and($data['body'])->toContain($student->name)
            ->and($data['dedup_key'])->toContain('student:'.$student->id)
            ->and($data['url'])->not->toBeEmpty()
            ->and($notification->student->is($student))->toBeTrue();

        expect(implode(' ', $mail->introLines))->toContain($student->name);

        return true;
    });
}

it('queues the halfway alert as mail and database for the instructor', function () {
    expect((new ReflectionClass(AttemptHalfwayAlertNotification::class))->implementsInterface(ShouldQueue::class))->toBeTrue();
});

it('emails and bells the instructor once when a quiz fail first reaches half of the allowed attempts', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 10);
    $question = halfwayKnowledgeQuestion($quiz);

    for ($i = 0; $i < 5; $i++) {
        $submission = submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);
        expect($submission)->toBeInstanceOf(QuizSubmission::class)
            ->and($submission->is_passed)->toBeFalse();
    }

    assertHalfwaySentToInstructor($graph['instructorUser'], $graph['student']);
    Notification::assertNotSentTo($graph['student'], AttemptHalfwayAlertNotification::class);
});

it('does not notify again for later quiz fails on the same activity', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 10);
    $question = halfwayKnowledgeQuestion($quiz);

    for ($i = 0; $i < 6; $i++) {
        submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);
    }

    Notification::assertSentToTimes($graph['instructorUser'], AttemptHalfwayAlertNotification::class, 1);
});

it('does not notify the instructor when the learner passes at the halfway attempt', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 10, passMark: 50);
    $question = halfwayKnowledgeQuestion($quiz);

    for ($i = 0; $i < 4; $i++) {
        submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);
    }

    $passed = submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: true);
    expect($passed)->toBeInstanceOf(QuizSubmission::class)
        ->and($passed->is_passed)->toBeTrue();

    Notification::assertNothingSent();
});

it('does not notify before the quiz halfway threshold', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 10);
    $question = halfwayKnowledgeQuestion($quiz);

    for ($i = 0; $i < 4; $i++) {
        submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);
    }

    Notification::assertNothingSent();
});

it('skips the halfway quiz alert when only one attempt is allowed', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 1);
    $question = halfwayKnowledgeQuestion($quiz);

    submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);

    Notification::assertNothingSent();
});

it('notifies the instructor after a takeoff quiz fail at the halfway attempt', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 2);
    QuizQuestion::create([
        'title' => 'Quantity takeoff',
        'type' => QuizQuestion::TYPE_TAKEOFF,
        'answer' => json_encode([]),
        'options' => json_encode([
            'line_items' => [[
                'key' => 'item-a',
                'item' => 'Drywall',
                'unit' => 'SF',
                'expected_qty' => 100,
            ]],
            'tolerance_percent' => 2,
        ]),
        'sort' => 1,
        'section_quiz_id' => $quiz->id,
    ]);

    $parser = Mockery::mock(QuantityTakeoffXlsxParser::class);
    $grader = Mockery::mock(QuantityTakeoffGradingService::class);
    $templates = Mockery::mock(QuantityTakeoffTemplateGenerator::class);
    $files = Mockery::mock(UsExperienceFileService::class);

    $files->shouldReceive('resolveLocalPath')->andReturn(['path' => '/tmp/boq.xlsx', 'temporary' => false]);
    $files->shouldReceive('forgetTemporary');
    $parser->shouldReceive('parse')->andReturn([
        ['key' => 'item-a', 'item' => 'Drywall', 'unit' => 'SF', 'expected_qty' => 1],
    ]);
    $grader->shouldReceive('grade')->andReturn([
        'marks_obtained' => 10,
        'is_correct' => false,
        'lines_correct' => 0,
        'lines_total' => 1,
        'lines_percent' => 0,
        'grading_breakdown' => [],
        'quantities' => ['item-a' => 1],
    ]);

    $service = new QuizTakeoffService($parser, $grader, $templates, $files, app(AttemptHalfwayAlertService::class));

    $submission = $service->submit($quiz->fresh(['quiz_questions']), $graph['student'], [
        'takeoff_pdf_url' => 'https://example.test/plans.pdf',
        'takeoff_pdf_name' => 'plans.pdf',
        'boq_xlsx_url' => 'https://example.test/boq.xlsx',
        'boq_xlsx_name' => 'boq.xlsx',
    ]);

    expect($submission)->toBeInstanceOf(QuizSubmission::class)
        ->and($submission->is_passed)->toBeFalse()
        ->and($submission->attempts)->toBe(1);

    assertHalfwaySentToInstructor($graph['instructorUser'], $graph['student']);
});

it('emails and bells the instructor when an exam fail first reaches half of the allowed attempts', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $exam = Exam::create([
        'title' => 'Final Exam',
        'instructor_id' => $graph['instructor']->id,
        'max_attempts' => 10,
        'pass_mark' => 70,
        'total_marks' => 10,
        'status' => 'published',
    ]);

    $question = ExamQuestion::create([
        'exam_id' => $exam->id,
        'question_type' => 'multiple_choice',
        'title' => 'Pick one',
        'marks' => 10,
        'sort' => 1,
    ]);

    $wrong = ExamQuestionOption::create([
        'exam_question_id' => $question->id,
        'option_text' => 'Wrong',
        'is_correct' => false,
        'sort' => 1,
    ]);
    ExamQuestionOption::create([
        'exam_question_id' => $question->id,
        'option_text' => 'Right',
        'is_correct' => true,
        'sort' => 2,
    ]);

    $attempts = app(ExamAttemptService::class);

    for ($i = 0; $i < 5; $i++) {
        $attempt = $attempts->startAttempt($graph['student'], $exam);
        $graded = $attempts->submitAttempt($attempt, [
            [
                'exam_question_id' => $question->id,
                'answer_data' => ['selected_option_id' => $wrong->id],
            ],
        ]);

        expect($graded->status)->toBe('completed')
            ->and($graded->is_passed)->toBeFalse();
    }

    assertHalfwaySentToInstructor($graph['instructorUser'], $graph['student']);
    Notification::assertSentTo($graph['instructorUser'], AttemptHalfwayAlertNotification::class, function (AttemptHalfwayAlertNotification $notification) use ($exam) {
        expect($notification->toArray($notification->student)['url'])->toContain('tab=attempts')
            ->and($notification->activityType)->toBe('exam')
            ->and($notification->activityId)->toBe($exam->id);

        return true;
    });
});

it('does not notify for an exam that is still waiting on manual grading', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $exam = Exam::create([
        'title' => 'Short Answer Exam',
        'instructor_id' => $graph['instructor']->id,
        'max_attempts' => 2,
        'pass_mark' => 70,
        'total_marks' => 10,
        'status' => 'published',
    ]);

    $question = ExamQuestion::create([
        'exam_id' => $exam->id,
        'question_type' => 'short_answer',
        'title' => 'Explain',
        'marks' => 10,
        'sort' => 1,
    ]);

    $attempts = app(ExamAttemptService::class);
    $attempt = $attempts->startAttempt($graph['student'], $exam);
    $submitted = $attempts->submitAttempt($attempt, [
        [
            'exam_question_id' => $question->id,
            'answer_data' => ['text' => 'not sure'],
        ],
    ]);

    expect($submitted->status)->toBe('submitted');
    Notification::assertNothingSent();

    $failed = $attempts->reviewAttempt($submitted, [$question->id => 0]);

    expect($failed->status)->toBe('completed')
        ->and($failed->is_passed)->toBeFalse();

    assertHalfwaySentToInstructor($graph['instructorUser'], $graph['student']);
});

it('does not notify when an exam is passed', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $exam = Exam::create([
        'title' => 'Easy Exam',
        'instructor_id' => $graph['instructor']->id,
        'max_attempts' => 2,
        'pass_mark' => 70,
        'total_marks' => 10,
        'status' => 'published',
    ]);

    $question = ExamQuestion::create([
        'exam_id' => $exam->id,
        'question_type' => 'multiple_choice',
        'title' => 'Pick one',
        'marks' => 10,
        'sort' => 1,
    ]);

    $right = ExamQuestionOption::create([
        'exam_question_id' => $question->id,
        'option_text' => 'Right',
        'is_correct' => true,
        'sort' => 1,
    ]);

    $attempts = app(ExamAttemptService::class);
    $attempt = $attempts->startAttempt($graph['student'], $exam);
    $graded = $attempts->submitAttempt($attempt, [
        [
            'exam_question_id' => $question->id,
            'answer_data' => ['selected_option_id' => $right->id],
        ],
    ]);

    expect($graded->is_passed)->toBeTrue();
    Notification::assertNothingSent();
});

it('notifies the instructor after a US Experience fail at the halfway attempt', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $plan = UsExperiencePlan::create([
        'course_id' => $graph['course']->id,
        'group_name' => 'Skills',
        'title' => 'Skill Level 1',
        'sort_order' => 1,
        'pass_mark' => 85,
        'max_attempts' => 10,
        'published' => true,
        'line_items' => [[
            'key' => 'item-a',
            'item' => 'Drywall',
            'unit' => 'SF',
            'expected_qty' => 100,
        ]],
    ]);

    $parser = Mockery::mock(QuantityTakeoffXlsxParser::class);
    $grader = Mockery::mock(QuantityTakeoffGradingService::class);
    $files = Mockery::mock(UsExperienceFileService::class);

    $files->shouldReceive('resolveLocalPath')->andReturn(['path' => '/tmp/boq.xlsx', 'temporary' => false]);
    $files->shouldReceive('forgetTemporary');
    $parser->shouldReceive('parse')->andReturn([
        ['key' => 'item-a', 'item' => 'Drywall', 'unit' => 'SF', 'expected_qty' => 1],
    ]);
    $grader->shouldReceive('grade')->andReturn([
        'marks_obtained' => 0,
        'is_correct' => false,
        'lines_correct' => 0,
        'lines_total' => 1,
        'lines_percent' => 0,
        'grading_breakdown' => [],
    ]);

    $service = new UsExperienceAttemptService($parser, $grader, $files, app(AttemptHalfwayAlertService::class));

    for ($i = 0; $i < 5; $i++) {
        $attempt = $service->submit(
            $plan,
            $graph['student'],
            'https://example.test/plans.pdf',
            'plans.pdf',
            'https://example.test/boq.xlsx',
            'boq.xlsx',
        );

        expect($attempt->status)->toBe(UsExperienceAttempt::STATUS_FAILED);
    }

    assertHalfwaySentToInstructor($graph['instructorUser'], $graph['student']);
    Notification::assertNotSentTo($graph['student'], AttemptHalfwayAlertNotification::class);
});

it('skips the alert when the instructor user is inactive', function () {
    Notification::fake();

    $graph = halfwayCourseGraph();
    $graph['instructorUser']->update(['status' => 0]);

    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 2);
    $question = halfwayKnowledgeQuestion($quiz);
    submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);

    Notification::assertNothingSent();
});

it('stores the unread bell payload for the instructor', function () {
    $graph = halfwayCourseGraph();
    $quiz = halfwayQuiz($graph['course'], $graph['section'], retake: 2);
    $question = halfwayKnowledgeQuestion($quiz);

    submitKnowledgeQuiz($quiz, $graph['student'], $question, correct: false);

    $graph['instructorUser']->refresh();
    $notification = $graph['instructorUser']->unreadNotifications->first();

    expect($graph['instructorUser']->unreadNotifications)->toHaveCount(1)
        ->and($notification->data['title'])->toBe('Halfway attempts: '.$graph['student']->name)
        ->and($notification->data['body'])->toContain($graph['student']->name)
        ->and($notification->data['url'])->not->toBeEmpty()
        ->and($notification->data['dedup_key'])->toContain('quiz');
});
