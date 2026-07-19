<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Course\CategoryChildController;
use App\Http\Controllers\Course\CourseAssignmentController;
use App\Http\Controllers\Course\CourseStripeSyncController;
use App\Http\Controllers\Course\CourseCategoryController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseCouponController;
use App\Http\Controllers\Course\CourseEnrollmentController;
use App\Http\Controllers\Course\CourseStudentProgressController;
use App\Http\Controllers\Course\TopPerformerController;
use App\Http\Controllers\Course\CourseFaqController;
use App\Http\Controllers\Course\CourseOutcomeController;
use App\Http\Controllers\Course\CourseRequirementController;
use App\Http\Controllers\Course\CurriculumController;
use App\Http\Controllers\Course\LessonResourceController;
use App\Http\Controllers\Course\QuestionController;
use App\Http\Controllers\Course\QuizController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\ProfessionalDevelopmentController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectLibraryController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\TrainerForumQuestionsController;

Route::prefix('dashboard/trainer')->group(function () {
   Route::get('/', [DashboardController::class, 'index'])->name('dashboard.trainer');

   Route::get('forum-questions', [TrainerForumQuestionsController::class, 'index'])->name('trainer.forum-questions.index');

   // Professional Development guides (admin + trainer managed content)
   Route::get('professional-development', [ProfessionalDevelopmentController::class, 'index'])->name('professional-development.index');
   Route::put('professional-development/{guide}', [ProfessionalDevelopmentController::class, 'update'])->name('professional-development.update');

   // Project Library (admin + trainer)
   Route::resource('project-categories', ProjectCategoryController::class)->only(['store', 'update', 'destroy']);
   Route::get('projects', [ProjectLibraryController::class, 'index'])->name('projects.index');
   Route::post('projects', [ProjectLibraryController::class, 'store'])->name('projects.store');
   Route::post('projects/{project}', [ProjectLibraryController::class, 'update'])->name('projects.update');
   Route::post('projects/{project}/publish', [ProjectLibraryController::class, 'togglePublish'])->name('projects.publish');
   Route::delete('projects/{project}', [ProjectLibraryController::class, 'destroy'])->name('projects.destroy');

   // Review/score learner project submissions
   Route::post('project-submissions/{submission}/score', [\App\Http\Controllers\ProjectSubmissionController::class, 'score'])->name('project-submissions.score');

   // Announcements (admin + trainer push to learners)
   Route::resource('announcements', AnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);

   // Resources library (admin + trainer)
   Route::get('learner-resources', [ResourceController::class, 'index'])->name('learner-resources.index');
   Route::post('learner-resources', [ResourceController::class, 'store'])->name('learner-resources.store');
   Route::post('learner-resources/{resource}', [ResourceController::class, 'update'])->name('learner-resources.update');
   Route::delete('learner-resources/{resource}', [ResourceController::class, 'destroy'])->name('learner-resources.destroy');

   // Help Center knowledge base (admin + trainer)
   Route::get('help-center', [HelpCenterController::class, 'index'])->name('help-center.index');
   Route::post('help-center', [HelpCenterController::class, 'store'])->name('help-center.store');
   Route::post('help-center/{help_center_article}', [HelpCenterController::class, 'update'])->name('help-center.update');
   Route::delete('help-center/{help_center_article}', [HelpCenterController::class, 'destroy'])->name('help-center.destroy');

   // Category (admin + trainer)
   // NOTE: These must be registered BEFORE the `courses` resource, otherwise the
   // `courses/{course}` wildcard would capture `courses/categories` and
   // `courses/category-child` and route them to the wrong controller.
   Route::resource('courses/categories', CourseCategoryController::class)->only(['index', 'store', 'destroy'])->names('categories');
   Route::post('courses/categories/update/{category}', [CourseCategoryController::class, 'update'])->name('categories.update');
   Route::post('courses/categories/sort', [CourseCategoryController::class, 'sort'])->name('categories.sort');
   Route::resource('courses/category-child', CategoryChildController::class)->only(['store', 'update', 'destroy'])->names('category-child');
   Route::post('courses/category-child/sort', [CategoryChildController::class, 'sort'])->name('category-child.sort');

   // Course coupons (admin + trainer). Trainers manage coupons for their own courses.
   Route::resource('courses/course/coupons', CourseCouponController::class)->only(['index', 'store', 'update', 'destroy'])->names('course-coupons');

   // Courses
   Route::resource('courses', CourseController::class)->except(['update', 'destroy']);
   Route::post('courses/{id}', [CourseController::class, 'update'])->name('courses.update');
   Route::post('courses/{id}/stripe/sync', CourseStripeSyncController::class)->name('courses.stripe.sync');
   Route::put('course/status/{id}', [CourseController::class, 'status'])->name('course.status')->middleware('smtpConfig', 'checkSmtp');

   Route::resource('course/faqs', CourseFaqController::class)->only(['store', 'update', 'destroy']);
   Route::resource('course/outcomes', CourseOutcomeController::class)->only(['store', 'update', 'destroy']);
   Route::resource('course/requirements', CourseRequirementController::class)->only(['store', 'update', 'destroy']);

   // curriculum
   Route::controller(CurriculumController::class)->group(function () {
      // Section route
      Route::post('section', 'section_store')->name('section.store');
      Route::put('section/{id}', 'section_update')->name('section.update');
      Route::delete('section/{id}', 'section_delete')->name('section.delete');
      Route::post('section/sort', 'section_sort')->name('section.sort');

      // lesson route
      Route::post('lesson', 'lesson_store')->name('lesson.store');
      Route::put('lesson/{id}', 'lesson_update')->name('lesson.update');
      Route::delete('lesson/{id}', 'lesson_delete')->name('lesson.delete');
      Route::post('lesson/sort', 'lesson_sort')->name('lesson.sort');
   });

   // assignment route
   Route::controller(CourseAssignmentController::class)->group(function () {
      Route::post('section/assignment', 'store')->name('assignment.store');
      Route::put('section/assignment/{id}', 'update')->name('assignment.update');
      Route::delete('section/assignment/{id}', 'destroy')->name('assignment.delete');
   });

   // Route::post('assignment/submission/grade/{id}', [AssignmentSubmissionController::class, 'update'])->name('assignment.submission.grade');

   // lesson resource route
   Route::resource('lesson/resources', LessonResourceController::class)->only(['store', 'update', 'destroy']);

   // section quiz
   Route::controller(QuizController::class)->group(function () {
      Route::post('section/quiz/store', 'store')->name('quiz.store');
      Route::delete('section/quiz/delete/{id}', 'destroy')->name('quiz.delete');
      Route::post('section/quiz/update/{id}', 'update')->name('quiz.update');
      Route::get('section/quiz/participant/result', 'result')->name('quiz.participant.result');
      Route::get('section/quiz/result/preview', 'result_preview')->name('quiz.result.preview');
   });

   // question route
   Route::controller(QuestionController::class)->group(function () {
      Route::post('quiz/question/store', 'store')->name('quiz.question.store');
      Route::post('quiz/questions/bulk', 'bulkStore')->name('quiz.questions.bulk.store');
      Route::put('quiz/question/update/{id}', 'update')->name('quiz.question.update');
      Route::delete('quiz/question/delete/{id}', 'delete')->name('quiz.question.delete');
      Route::post('quiz/question/sort/', 'sort')->name('quiz.question.sort');
   });

   // live classes
   Route::resource('live-class', LiveClassController::class)->only(['store', 'update', 'destroy']);

   // course enrolment
   Route::get('enrollments/courses', [CourseEnrollmentController::class, 'index'])->name('course-enrollments.index');

   // student progress (trainers: own courses; admin: all)
   Route::get('student-progress', [CourseStudentProgressController::class, 'index'])->name('student-progress.index');
   Route::get('student-progress/{course}', [CourseStudentProgressController::class, 'show'])->name('student-progress.show');

   Route::get('top-performers', [TopPerformerController::class, 'index'])->name('top-performers.index');

   // settings
   Route::prefix('settings/account')->group(function () {
      Route::get('/', [SettingController::class, 'account'])->name('settings.account');
      Route::post('profile', [SettingController::class, 'profile_update'])->name('account.profile');
   });
});
