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

    public function assertTakeoff(QuizQuestion $question): void
    {
        if (! $question->isTakeoff()) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareQuestionPayload(array $data, ?QuizQuestion $existing = null): array
    {
        $existingOptions = $existing?->decodedOptions() ?? [];
        $drawings = $this->drawingsFromOptions($existingOptions);
        $incomingPdfUrl = filled($data['pdf_url'] ?? null) ? $data['pdf_url'] : null;
        $incomingPdfName = $data['pdf_name'] ?? 'plans.pdf';

        if ($incomingPdfUrl && ! $this->drawingExists($drawings, $incomingPdfUrl)) {
            $drawings[] = [
                'file_url' => $incomingPdfUrl,
                'file_name' => $incomingPdfName,
            ];
        }

        if ($drawings === []) {
            throw ValidationException::withMessages([
                'pdf_url' => 'Upload the takeoff PDF plans.',
            ]);
        }

        $answerKeyUrl = filled($data['answer_key_url'] ?? null)
            ? $data['answer_key_url']
            : ($existingOptions['answer_key_file_url'] ?? null);
        $answerKeyName = filled($data['answer_key_name'] ?? null)
            ? $data['answer_key_name']
            : ($existingOptions['answer_key_file_name'] ?? null);
        $lineItems = $existingOptions['line_items'] ?? [];

        if ($answerKeyUrl && $answerKeyUrl !== ($existingOptions['answer_key_file_url'] ?? null)) {
            $lineItems = $this->mergeLineOverrides($this->parseAnswerKey($answerKeyUrl), $lineItems);
        }

        if ($lineItems === []) {
            throw ValidationException::withMessages([
                'answer_key_url' => 'Upload an Excel answer key with a filled Quantity Summary.',
            ]);
        }

        $tolerance = $data['tolerance_percent'] ?? $existingOptions['tolerance_percent'] ?? config('us_experience.default_tolerance_percent', 2);
        $tolerance = min(100, max(0, (float) $tolerance));
        $first = $drawings[0];

        $data['title'] = filled($data['title'] ?? null) ? $data['title'] : 'Quantity takeoff';
        $data['options'] = array_merge($existingOptions, [
            'drawings' => $drawings,
            'pdf_url' => $first['file_url'],
            'pdf_name' => $first['file_name'],
            'answer_key_file_url' => $answerKeyUrl,
            'answer_key_file_name' => $answerKeyName,
            'line_items' => $lineItems,
            'tolerance_percent' => $tolerance,
            'parsed_at' => $existingOptions['parsed_at'] ?? now()->toIso8601String(),
        ]);

        if ($answerKeyUrl && $answerKeyUrl !== ($existingOptions['answer_key_file_url'] ?? null)) {
            $data['options']['parsed_at'] = now()->toIso8601String();
        }

        $data['answer'] = [];

        unset($data['pdf_url'], $data['pdf_name'], $data['answer_key_url'], $data['answer_key_name'], $data['tolerance_percent']);

        return $data;
    }

    public function addDrawing(QuizQuestion $question, string $fileUrl, string $fileName): QuizQuestion
    {
        $this->assertTakeoff($question);
        $drawings = $this->drawingsFromOptions($question->decodedOptions());

        if (! $this->drawingExists($drawings, $fileUrl)) {
            $drawings[] = [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
            ];
        }

        return $this->persistOptions($question, ['drawings' => $drawings]);
    }

    public function removeDrawing(QuizQuestion $question, string $fileUrl): QuizQuestion
    {
        $this->assertTakeoff($question);
        $drawings = array_values(array_filter(
            $this->drawingsFromOptions($question->decodedOptions()),
            fn (array $drawing) => ($drawing['file_url'] ?? '') !== $fileUrl
        ));

        return $this->persistOptions($question, ['drawings' => $drawings]);
    }

    /**
     * @return array{line_items: list<array<string, mixed>>, line_count: int}
     */
    public function importAnswerKey(QuizQuestion $question, string $fileUrl, string $fileName): array
    {
        $this->assertTakeoff($question);

        try {
            $lineItems = $this->mergeLineOverrides(
                $this->parseAnswerKey($fileUrl),
                $question->takeoffLineItems(),
            );
        } catch (ValidationException $exception) {
            throw new InvalidArgumentException(
                collect($exception->errors())->flatten()->first() ?: 'Could not import the answer key.'
            );
        }

        $this->persistOptions($question, [
            'answer_key_file_url' => $fileUrl,
            'answer_key_file_name' => $fileName,
            'line_items' => $lineItems,
            'parsed_at' => now()->toIso8601String(),
        ]);

        return [
            'line_items' => $lineItems,
            'line_count' => count($lineItems),
        ];
    }

    public function saveTutorialVideo(QuizQuestion $question, string $videoUrl, string $videoName): QuizQuestion
    {
        $this->assertTakeoff($question);

        return $this->persistOptions($question, [
            'tutorial_video_url' => $videoUrl,
            'tutorial_video_name' => $videoName,
        ]);
    }

    /**
     * @param  array<int, array{key: string, tolerance_override?: float|null, tolerance_override_mode?: string|null}>  $tolerances
     */
    public function saveLineTolerances(QuizQuestion $question, array $tolerances): QuizQuestion
    {
        $this->assertTakeoff($question);
        $lineItems = $question->takeoffLineItems();
        $toleranceMap = collect($tolerances)->keyBy('key');

        foreach ($lineItems as &$line) {
            if (! $toleranceMap->has($line['key'])) {
                continue;
            }

            $this->applyLineTolerance($line, $toleranceMap[$line['key']]);
        }
        unset($line);

        return $this->persistOptions($question, ['line_items' => $lineItems]);
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
            defaultPercentTolerance: $question->takeoffTolerancePercent(),
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
        $drawings = $this->drawingsFromOptions($options);
        $answerKeyUrl = $options['answer_key_file_url'] ?? null;
        $lineItems = $options['line_items'] ?? [];

        if ($drawings === [] || ! $answerKeyUrl || $lineItems === []) {
            throw new InvalidArgumentException('This takeoff quiz is not ready to download.');
        }

        $question->loadMissing('lesson_quiz');

        $zipPath = $this->files->makeTempFile('zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Unable to build the download pack.');
        }

        $cleanup = [$zipPath];
        $usedNames = [];

        try {
            foreach ($drawings as $index => $drawing) {
                $resolved = $this->files->resolveLocalPath($drawing['file_url']);
                if ($resolved['temporary']) {
                    $cleanup[] = $resolved['path'];
                }

                $name = $this->uniqueZipName($usedNames, $drawing['file_name'] ?? ('drawing-'.($index + 1).'.pdf'), 'drawings');
                $zip->addFile($resolved['path'], $name);
            }

            $answerKey = $this->files->resolveLocalPath($answerKeyUrl);
            if ($answerKey['temporary']) {
                $cleanup[] = $answerKey['path'];
            }

            $templatePath = $this->files->makeTempFile('xlsx');
            $cleanup[] = $templatePath;
            $this->templates->generateBlankTemplate($answerKey['path'], $templatePath, $lineItems);
            $zip->addFile($templatePath, $this->uniqueZipName($usedNames, 'student-template.xlsx', 'template'));
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

    /**
     * @return list<array{file_url: string, file_name: string}>
     */
    public function drawingsFromOptions(array $options): array
    {
        $drawings = $options['drawings'] ?? null;

        if (is_array($drawings)) {
            return array_values(array_filter(
                $drawings,
                fn ($drawing) => is_array($drawing) && filled($drawing['file_url'] ?? null)
            ));
        }

        if (filled($options['pdf_url'] ?? null)) {
            return [[
                'file_url' => (string) $options['pdf_url'],
                'file_name' => (string) ($options['pdf_name'] ?? 'plans.pdf'),
            ]];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function persistOptions(QuizQuestion $question, array $patch): QuizQuestion
    {
        $options = array_merge($question->decodedOptions(), $patch);
        $drawings = $this->drawingsFromOptions($options);
        $options['drawings'] = $drawings;
        $options['pdf_url'] = $drawings[0]['file_url'] ?? null;
        $options['pdf_name'] = $drawings[0]['file_name'] ?? null;

        $question->update(['options' => json_encode($options)]);

        return $question->fresh();
    }

    /**
     * @param  list<array{file_url: string, file_name?: string}>  $drawings
     */
    private function drawingExists(array $drawings, string $fileUrl): bool
    {
        foreach ($drawings as $drawing) {
            if (($drawing['file_url'] ?? '') === $fileUrl) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    private function mergeLineOverrides(array $lineItems, array $existing): array
    {
        $oldOverrides = collect($existing)->mapWithKeys(fn (array $line) => [
            $line['key'] => [
                'tolerance_override' => $line['tolerance_override'] ?? null,
                'tolerance_override_mode' => $line['tolerance_override_mode'] ?? null,
            ],
        ]);

        foreach ($lineItems as &$line) {
            if (! $oldOverrides->has($line['key'])) {
                continue;
            }

            $line['tolerance_override'] = $oldOverrides[$line['key']]['tolerance_override'];
            $mode = $oldOverrides[$line['key']]['tolerance_override_mode'];
            if ($mode) {
                $line['tolerance_override_mode'] = $mode;
            }
        }
        unset($line);

        return $lineItems;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array{tolerance_override?: float|null, tolerance_override_mode?: string|null}  $incoming
     */
    private function applyLineTolerance(array &$line, array $incoming): void
    {
        $override = $incoming['tolerance_override'] ?? null;

        if ($override === null || $override === '') {
            $line['tolerance_override'] = null;
            unset($line['tolerance_override_mode']);

            return;
        }

        $mode = (string) ($incoming['tolerance_override_mode'] ?? 'percent');
        $line['tolerance_override'] = (float) $override;
        $line['tolerance_override_mode'] = in_array($mode, ['percent', 'absolute'], true) ? $mode : 'percent';
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function uniqueZipName(array &$usedNames, string $original, string $folder): string
    {
        $base = basename($original) ?: 'file';
        $name = $folder.'/'.$base;
        $i = 1;

        while (isset($usedNames[$name])) {
            $name = $folder.'/'.$i.'-'.$base;
            $i++;
        }

        $usedNames[$name] = true;

        return $name;
    }
}
