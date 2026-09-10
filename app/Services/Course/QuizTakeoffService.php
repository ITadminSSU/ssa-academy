<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\QuestionAnswer;
use App\Models\Course\QuizQuestion;
use App\Models\Course\QuizSubmission;
use App\Models\Course\SectionQuiz;
use App\Models\User;
use App\Services\UsExperience\UsExperienceFileService;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Exam\Services\QuantityTakeoffGradingService;
use Modules\Exam\Services\QuantityTakeoffTemplateGenerator;
use Modules\Exam\Services\QuantityTakeoffXlsxParser;

class QuizTakeoffService
{
    public function __construct(
        private QuantityTakeoffXlsxParser $parser,
        private QuantityTakeoffGradingService $grader,
        private QuantityTakeoffTemplateGenerator $templates,
        private UsExperienceFileService $files,
    ) {}

    /**
     * @param  list<string>  $existingTypes
     * @param  list<string>  $incomingTypes
     */
    public function assertQuestionMix(array $existingTypes, array $incomingTypes): void
    {
        $incomingHasTakeoff = in_array(QuizQuestion::TYPE_TAKEOFF, $incomingTypes, true);
        $existingHasTakeoff = in_array(QuizQuestion::TYPE_TAKEOFF, $existingTypes, true);

        if ($existingHasTakeoff) {
            throw ValidationException::withMessages([
                'questions' => 'This quiz is a quantity takeoff. Remove it before adding other questions.',
            ]);
        }

        if ($incomingHasTakeoff && count($incomingTypes) !== 1) {
            throw ValidationException::withMessages([
                'questions' => 'A quantity takeoff quiz can only contain that one question.',
            ]);
        }

        if ($incomingHasTakeoff && count($existingTypes) > 0) {
            throw ValidationException::withMessages([
                'questions' => 'Remove the existing questions before converting this quiz to a quantity takeoff.',
            ]);
        }
    }

    public function assertCanSave(SectionQuiz $quiz, array $incomingTypes, ?int $ignoreQuestionId = null): void
    {
        $quiz->loadMissing('quiz_questions');

        $existing = $quiz->quiz_questions
            ->when($ignoreQuestionId, fn ($questions) => $questions->where('id', '!=', $ignoreQuestionId))
            ->pluck('type')
            ->values()
            ->all();

        $this->assertQuestionMix($existing, $incomingTypes);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareQuestionPayload(array $data, ?QuizQuestion $existing = null): array
    {
        $existingOptions = $existing?->decodedOptions() ?? [];
        $pdfUrl = $data['pdf_url'] ?? $existingOptions['pdf_url'] ?? null;
        $pdfName = $data['pdf_name'] ?? $existingOptions['pdf_name'] ?? null;
        $answerKeyUrl = $data['answer_key_url'] ?? $existingOptions['answer_key_file_url'] ?? null;
        $answerKeyName = $data['answer_key_name'] ?? $existingOptions['answer_key_file_name'] ?? null;

        if (! $pdfUrl || ! $pdfName) {
            throw ValidationException::withMessages([
                'pdf_url' => 'Upload the takeoff PDF plans.',
            ]);
        }

        $lineItems = $existingOptions['line_items'] ?? [];

        if ($answerKeyUrl && $answerKeyUrl !== ($existingOptions['answer_key_file_url'] ?? null)) {
            $lineItems = $this->parseAnswerKey($answerKeyUrl);
        }

        if ($lineItems === []) {
            throw ValidationException::withMessages([
                'answer_key_url' => 'Upload an Excel answer key with a filled Quantity Summary.',
            ]);
        }

        $data['title'] = filled($data['title'] ?? null) ? $data['title'] : 'Quantity takeoff';
        $data['options'] = [
            'pdf_url' => $pdfUrl,
            'pdf_name' => $pdfName,
            'answer_key_file_url' => $answerKeyUrl,
            'answer_key_file_name' => $answerKeyName,
            'line_items' => $lineItems,
            'parsed_at' => now()->toIso8601String(),
        ];
        $data['answer'] = [];

        unset($data['pdf_url'], $data['pdf_name'], $data['answer_key_url'], $data['answer_key_name']);

        return $data;
    }

    /**
     * @return list<array{key: string, item: string, unit: string, expected_qty: float}>
     */
    public function parseAnswerKey(string $fileUrl): array
    {
        $resolved = $this->files->resolveLocalPath($fileUrl);

        try {
            return $this->parser->parse($resolved['path']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'answer_key_url' => $exception->getMessage(),
            ]);
        } finally {
            $this->files->forgetTemporary($resolved);
        }
    }

    /**
     * @return array{
     *     marks_obtained: float,
     *     is_correct: bool,
     *     lines_correct: int,
     *     lines_total: int,
     *     lines_percent: float,
     *     grading_breakdown: array<int, array<string, mixed>>,
     *     quantities: array<string, float|null>
     * }
     */
    public function gradeQuestion(QuizQuestion $question, string $boqXlsxUrl): array
    {
        $lineItems = $question->takeoffLineItems();

        if ($lineItems === []) {
            throw new InvalidArgumentException('This takeoff quiz does not have an imported answer key yet.');
        }

        $resolved = $this->files->resolveLocalPath($boqXlsxUrl);

        try {
            $studentLines = $this->parser->parse($resolved['path']);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                'Could not read the submitted Excel BOQ. Use the blank template and fill Quantity Summary. '.$exception->getMessage()
            );
        } finally {
            $this->files->forgetTemporary($resolved);
        }

        $quantities = [];
        foreach ($studentLines as $line) {
            $quantities[$line['key']] = $line['expected_qty'];
        }

        $quiz = $question->lesson_quiz ?: SectionQuiz::query()->find($question->section_quiz_id);
        $totalMarks = (float) ($quiz?->total_mark ?: config('us_experience.default_total_marks', 100));

        $result = $this->grader->grade(
            $lineItems,
            ['quantities' => $quantities],
            $totalMarks,
            defaultPercentTolerance: (float) config('us_experience.default_tolerance_percent', 2),
        );

        $result['quantities'] = $quantities;

        return $result;
    }

    /**
     * @return array{path: string, name: string}
     */
    public function buildStudentPack(QuizQuestion $question): array
    {
        $options = $question->decodedOptions();
        $pdfUrl = $options['pdf_url'] ?? null;
        $answerKeyUrl = $options['answer_key_file_url'] ?? null;
        $lineItems = $options['line_items'] ?? [];

        if (! $pdfUrl || ! $answerKeyUrl || $lineItems === []) {
            throw new InvalidArgumentException('This takeoff quiz is not ready to download.');
        }

        $question->loadMissing('lesson_quiz');

        $zipPath = $this->files->makeTempFile('zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Unable to build the download pack.');
        }

        $cleanup = [$zipPath];

        try {
            $pdf = $this->files->resolveLocalPath($pdfUrl);
            if ($pdf['temporary']) {
                $cleanup[] = $pdf['path'];
            }
            $pdfName = basename((string) ($options['pdf_name'] ?? 'plans.pdf')) ?: 'plans.pdf';
            $zip->addFile($pdf['path'], 'drawings/'.$pdfName);

            $answerKey = $this->files->resolveLocalPath($answerKeyUrl);
            if ($answerKey['temporary']) {
                $cleanup[] = $answerKey['path'];
            }

            $templatePath = $this->files->makeTempFile('xlsx');
            $cleanup[] = $templatePath;
            $this->templates->generateBlankTemplate($answerKey['path'], $templatePath, $lineItems);
            $zip->addFile($templatePath, 'template/student-template.xlsx');
            $zip->close();
        } catch (\Throwable $exception) {
            @$zip->close();
            foreach ($cleanup as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            throw $exception instanceof InvalidArgumentException
                ? $exception
                : new InvalidArgumentException($exception->getMessage());
        }

        foreach ($cleanup as $path) {
            if ($path !== $zipPath && is_file($path)) {
                @unlink($path);
            }
        }

        $quizTitle = $question->lesson_quiz?->title ?: 'takeoff-quiz';
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $quizTitle) ?: 'quiz';

        return [
            'path' => $zipPath,
            'name' => $slug.'-pack.zip',
        ];
    }

    public function sanitizeCourseForPlayer(Course $course): void
    {
        $course->loadMissing('sections.section_quizzes.quiz_questions');

        foreach ($course->sections as $section) {
            foreach ($section->section_quizzes as $quiz) {
                foreach ($quiz->quiz_questions as $question) {
                    $question->hideTakeoffAnswerKey();
                }
            }
        }
    }

    public function sanitizeQuizForPlayer(SectionQuiz $quiz): void
    {
        $quiz->loadMissing('quiz_questions');

        foreach ($quiz->quiz_questions as $question) {
            $question->hideTakeoffAnswerKey();
        }
    }

    public function takeoffQuestion(SectionQuiz $quiz): ?QuizQuestion
    {
        $quiz->loadMissing('quiz_questions');

        return $quiz->quiz_questions->first(fn (QuizQuestion $question) => $question->isTakeoff());
    }

    /**
     * @param  array{takeoff_pdf_url: string, takeoff_pdf_name: string, boq_xlsx_url: string, boq_xlsx_name: string}  $files
     */
    public function submit(SectionQuiz $quiz, User $user, array $files): QuizSubmission|false
    {
        $question = $this->takeoffQuestion($quiz);

        if (! $question) {
            throw new InvalidArgumentException('This quiz is not a quantity takeoff.');
        }

        $submission = QuizSubmission::query()
            ->where('user_id', $user->id)
            ->where('section_quiz_id', $quiz->id)
            ->first();

        if ($submission) {
            if ($submission->attempts >= $quiz->retake) {
                return false;
            }

            $submission->increment('attempts');
        } else {
            $submission = QuizSubmission::query()->create([
                'section_quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'attempts' => 1,
                'correct_answers' => 0,
                'incorrect_answers' => 0,
                'total_marks' => 0,
                'is_passed' => false,
            ]);
        }

        $result = $this->gradeQuestion($question, $files['boq_xlsx_url']);
        $passed = $result['marks_obtained'] >= (float) $quiz->pass_mark;

        QuestionAnswer::query()->create([
            'answers' => json_encode([
                'takeoff_pdf_url' => $files['takeoff_pdf_url'],
                'takeoff_pdf_name' => $files['takeoff_pdf_name'],
                'boq_xlsx_url' => $files['boq_xlsx_url'],
                'boq_xlsx_name' => $files['boq_xlsx_name'],
                'quantities' => $result['quantities'],
                'grading_breakdown' => $result['grading_breakdown'],
                'lines_correct' => $result['lines_correct'],
                'lines_total' => $result['lines_total'],
                'lines_percent' => $result['lines_percent'],
            ]),
            'is_correct' => $passed,
            'user_id' => $user->id,
            'quiz_question_id' => $question->id,
        ]);

        $submission->update([
            'correct_answers' => $result['lines_correct'],
            'incorrect_answers' => max(0, $result['lines_total'] - $result['lines_correct']),
            'total_marks' => $result['marks_obtained'],
            'is_passed' => $passed,
        ]);

        return $submission->fresh();
    }
}
