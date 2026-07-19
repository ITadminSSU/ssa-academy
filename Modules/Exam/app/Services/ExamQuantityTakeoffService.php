<?php

namespace Modules\Exam\Services;

use App\Models\ChunkedUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamQuestion;

class ExamQuantityTakeoffService
{
    public function __construct(
        private QuantityTakeoffXlsxParser $parser,
        private QuantityTakeoffAnalyticsService $analyticsService,
    ) {}

    /**
     * @return array{line_items: array<int, array<string, mixed>>, line_count: int}
     */
    public function importAnswerKey(Exam $exam, string $fileUrl, string $fileName): array
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            throw new InvalidArgumentException('This exam is not configured as a quantity take-off exam.');
        }

        $filePath = $this->resolveFilePath($fileUrl);
        $lineItems = $this->parser->parse($filePath);
        $existingConfig = $exam->takeoff_config ?? [];
        $oldOverrides = collect($existingConfig['line_items'] ?? [])
            ->mapWithKeys(fn (array $line) => [$line['key'] => $line['tolerance_override'] ?? null]);

        foreach ($lineItems as &$line) {
            if ($oldOverrides->has($line['key'])) {
                $line['tolerance_override'] = $oldOverrides[$line['key']];
            }
        }
        unset($line);

        return DB::transaction(function () use ($exam, $fileUrl, $fileName, $lineItems) {
            $existingConfig = $exam->takeoff_config ?? [];

            $takeoffConfig = [
                'answer_key_file_url' => $fileUrl,
                'answer_key_file_name' => $fileName,
                'line_items' => $lineItems,
                'parsed_at' => now()->toIso8601String(),
                'tutorial_video_url' => $existingConfig['tutorial_video_url'] ?? null,
                'tutorial_video_name' => $existingConfig['tutorial_video_name'] ?? null,
                'student_template_file_url' => $existingConfig['student_template_file_url'] ?? null,
                'student_template_file_name' => $existingConfig['student_template_file_name'] ?? null,
            ];

            $exam->update([
                'takeoff_config' => $takeoffConfig,
                'pass_mark' => config('quantity_takeoff.default_pass_mark', 85),
                'total_marks' => config('quantity_takeoff.default_total_marks', 100),
                'total_questions' => 1,
            ]);

            $this->syncTakeoffQuestion($exam->fresh(), $lineItems);

            return [
                'line_items' => $lineItems,
                'line_count' => count($lineItems),
            ];
        });
    }

    /**
     * @param array<int, array{key: string, item: string, unit: string, expected_qty: float}> $lineItems
     */
    public function syncTakeoffQuestion(Exam $exam, array $lineItems): ExamQuestion
    {
        $publicLineItems = collect($lineItems)->map(fn (array $line) => [
            'key' => $line['key'],
            'item' => $line['item'],
            'unit' => $line['unit'],
        ])->values()->all();

        $question = $exam->questions()->where('question_type', 'quantity_takeoff')->first();

        $payload = [
            'exam_id' => $exam->id,
            'question_type' => 'quantity_takeoff',
            'title' => 'Quantity Take-Off',
            'description' => 'Enter the quantity for each line item based on the reference drawings.',
            'marks' => config('quantity_takeoff.default_total_marks', 100),
            'sort' => 0,
            'options' => [
                'line_items' => $publicLineItems,
            ],
        ];

        if ($question) {
            $question->update($payload);
        } else {
            $question = ExamQuestion::create($payload);
        }

        return $question;
    }

    /**
     * @return array<int, array{key: string, item: string, unit: string}>
     */
    public function publicLineItems(Exam $exam): array
    {
        return collect($exam->takeoff_config['line_items'] ?? [])
            ->map(fn (array $line) => [
                'key' => $line['key'],
                'item' => $line['item'],
                'unit' => $line['unit'],
            ])
            ->values()
            ->all();
    }

    public function isReady(Exam $exam): bool
    {
        return $exam->exam_mode === 'quantity_takeoff'
            && !empty($exam->takeoff_config['line_items']);
    }

    public function saveTutorialVideo(Exam $exam, string $videoUrl, string $videoName): Exam
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            throw new InvalidArgumentException('This exam is not configured as a quantity take-off exam.');
        }

        $config = $exam->takeoff_config ?? [];
        $config['tutorial_video_url'] = $videoUrl;
        $config['tutorial_video_name'] = $videoName;

        $exam->update(['takeoff_config' => $config]);

        return $exam->fresh();
    }

    /**
     * @return array{url: string, name: string}|null
     */
    public function publicTutorialVideo(Exam $exam): ?array
    {
        $url = $exam->takeoff_config['tutorial_video_url'] ?? null;

        if (!$url) {
            return null;
        }

        return [
            'url' => $url,
            'name' => $exam->takeoff_config['tutorial_video_name'] ?? 'Walkthrough video',
        ];
    }

    /**
     * @param array<int, array{key: string, tolerance_override?: float|null}> $tolerances
     */
    public function saveLineTolerances(Exam $exam, array $tolerances): Exam
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            throw new InvalidArgumentException('This exam is not configured as a quantity take-off exam.');
        }

        $config = $exam->takeoff_config ?? [];
        $lineItems = $config['line_items'] ?? [];
        $toleranceMap = collect($tolerances)->keyBy('key');

        foreach ($lineItems as &$line) {
            if (!$toleranceMap->has($line['key'])) {
                continue;
            }

            $override = $toleranceMap[$line['key']]['tolerance_override'] ?? null;
            $line['tolerance_override'] = $override === null || $override === '' ? null : (float) $override;
        }
        unset($line);

        $config['line_items'] = $lineItems;
        $exam->update(['takeoff_config' => $config]);

        return $exam->fresh();
    }

    /**
     * @return array<int, array{key: string, item: string, unit: string, attempts: int, misses: int, miss_rate: float}>
     */
    public function lineMissAnalytics(Exam $exam): array
    {
        return $this->analyticsService->getLineMissAnalytics($exam);
    }

    public function saveStudentTemplate(Exam $exam, string $fileUrl, string $fileName): Exam
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            throw new InvalidArgumentException('This exam is not configured as a quantity take-off exam.');
        }

        $config = $exam->takeoff_config ?? [];
        $config['student_template_file_url'] = $fileUrl;
        $config['student_template_file_name'] = $fileName;

        $exam->update(['takeoff_config' => $config]);

        return $exam->fresh();
    }

    /**
     * @return array{url: string, name: string}|null
     */
    public function publicStudentTemplate(Exam $exam): ?array
    {
        $url = $exam->takeoff_config['student_template_file_url'] ?? null;

        if (!$url) {
            return null;
        }

        return [
            'url' => $url,
            'name' => $exam->takeoff_config['student_template_file_name'] ?? 'Student template.xlsx',
        ];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function studentTemplateDownload(Exam $exam): array
    {
        if ($exam->exam_mode !== 'quantity_takeoff') {
            throw new InvalidArgumentException('This exam is not configured as a quantity take-off exam.');
        }

        $template = $this->publicStudentTemplate($exam);

        if (!$template) {
            throw new InvalidArgumentException('The trainer has not uploaded a blank student template for this exam yet.');
        }

        return [
            'path' => $this->resolveFilePath($template['url']),
            'name' => $template['name'],
        ];
    }

    public function allowsStudentSupportingUpload(): bool
    {
        return (bool) config('quantity_takeoff.allow_student_supporting_upload', true);
    }

    /**
     * @return array{path: string, name: string}
     * @deprecated Trainer-uploaded blank templates are used instead.
     */
    public function generateStudentTemplate(Exam $exam): array
    {
        return $this->studentTemplateDownload($exam);
    }

    private function resolveFilePath(string $fileUrl): string
    {
        $upload = ChunkedUpload::query()->where('file_url', $fileUrl)->first();

        if ($upload) {
            return Storage::disk($upload->disk)->path($upload->file_path);
        }

        $publicPath = parse_url($fileUrl, PHP_URL_PATH);
        if (is_string($publicPath)) {
            $storagePath = public_path(ltrim($publicPath, '/'));
            if (is_file($storagePath)) {
                return $storagePath;
            }
        }

        throw new InvalidArgumentException('Could not locate the uploaded answer key file.');
    }
}
