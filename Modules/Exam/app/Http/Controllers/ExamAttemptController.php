<?php

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Http\Requests\ExamAttemptRequest;
use Modules\Exam\Http\Requests\SaveExamTakeoffOverridesRequest;
use Modules\Exam\Services\ExamAttemptService;
use Modules\Exam\Services\ExamEnrollmentService;
use Modules\Exam\Services\ExamQuantityTakeoffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ExamAttemptController extends Controller
{
   public function __construct(
      protected ExamAttemptService $attemptService,
      protected ExamEnrollmentService $enrollmentService
   ) {}

   /**
    * Start a new exam attempt
    */
   public function start(ExamAttemptRequest $request, Exam $exam)
   {
      $user = Auth::user();

      if ($exam->isQuantityTakeoff() && !app(ExamQuantityTakeoffService::class)->isReady($exam)) {
         return back()->with('error', 'This quantity take-off exam is not ready yet. The trainer must upload an answer key first.');
      }

      $attempt = $this->attemptService->startAttempt($user, $exam);

      if (!$attempt) {
         return back()->with('error', 'Unable to start exam. You may have reached the maximum number of attempts.');
      }

      return redirect()
         ->route('exam-attempts.take', $attempt->id)
         ->with('success', 'Exam started. Good luck!');
   }

   /**
    * Take the exam (show questions)
    */
   public function take(ExamAttempt $attempt)
   {
      $attempt->load(['exam.questions.question_options', 'exam.resources']);

      if ($attempt->exam?->isQuantityTakeoff()) {
         $takeoffService = app(ExamQuantityTakeoffService::class);
         $attempt->exam->makeHidden(['takeoff_config']);
         $lineItems = $takeoffService->publicLineItems($attempt->exam);
         $studentTemplate = $takeoffService->publicStudentTemplate($attempt->exam);

         return Inertia::render('student/exam/quantity-takeoff-attempt', [
            'attempt' => $attempt,
            'lineItems' => $lineItems,
            'gradingRules' => [
               'tolerance_percent' => config('quantity_takeoff.tolerance_percent', 1),
               'unit_floors' => config('quantity_takeoff.unit_floors', []),
               'pass_mark' => $attempt->exam->pass_mark,
            ],
            'templateDownloadUrl' => $studentTemplate
               ? route('exam-attempts.takeoff-template', $attempt->id)
               : null,
            'allowSupportingUpload' => $takeoffService->allowsStudentSupportingUpload(),
         ]);
      }

      return Inertia::render('student/exam/attempt', [
         'attempt' => $attempt,
      ]);
   }

   /**
    * Submit exam answers
    */
   public function submit(ExamAttemptRequest $request, ExamAttempt $attempt)
   {
      // Check if attempt is in progress
      if ($attempt->status !== 'in_progress') {
         return back()->with('error', 'This attempt has already been submitted.');
      }

      $answers = $request->input('answers', []);
      $attempt = $this->attemptService->submitAttempt($attempt, $answers);

      return redirect()
         ->route('student.exam.show', [
            'id' => $attempt->exam_id,
            'tab' => 'attempts',
            'attempt' => $attempt->id
         ])
         ->with('success', 'Exam submitted successfully!');
   }

   /**
    * Abandon an in-progress attempt
    */
   public function abandon(ExamAttempt $attempt)
   {
      if ($attempt->status !== 'in_progress') {
         return back()->with('error', 'This attempt is not in progress.');
      }

      $this->attemptService->abandonAttempt($attempt);

      return redirect()
         ->route('exams.edit', [
            'exam' => $attempt->exam_id,
            'tab' => 'attempts',
            'attempt' => $attempt->id
         ])
         ->with('info', 'Exam attempt abandoned.');
   }

   /**
    * Grade exam attempt (Admin/Instructor only)
    */
   public function grade(Request $request, ExamAttempt $attempt)
   {
      // Validate manual grades
      $manualGrades = $request->input('manual_grades', []);

      $this->attemptService->reviewAttempt($attempt, $manualGrades);

      return redirect(route('exams.edit', ['exam' => $attempt->exam_id, 'tab' => 'attempts']))
         ->with('success', 'Exam attempt graded successfully!');
   }

   public function saveTakeoffOverrides(SaveExamTakeoffOverridesRequest $request, ExamAttempt $attempt)
   {
      $this->attemptService->applyTakeoffLineOverrides($attempt, $request->validated('line_overrides'));

      return redirect(route('exams.edit', [
         'exam' => $attempt->exam_id,
         'tab' => 'attempts',
         'review' => $attempt->id,
      ]))->with('success', 'Line overrides saved and attempt score recalculated.');
   }

   public function downloadTakeoffTemplate(ExamAttempt $attempt)
   {
      if ((int) $attempt->user_id !== (int) Auth::id()) {
         abort(403);
      }

      if ($attempt->status !== 'in_progress') {
         return back()->with('error', 'Template download is only available during an active attempt.');
      }

      try {
         $template = app(ExamQuantityTakeoffService::class)->studentTemplateDownload($attempt->exam);
      } catch (\InvalidArgumentException $exception) {
         return back()->with('error', $exception->getMessage());
      }

      return response()->download($template['path'], $template['name'])->deleteFileAfterSend(false);
   }
}
