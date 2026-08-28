<?php

namespace App\Services\UsExperience;

use App\Models\Course\Course;
use App\Models\Course\UsExperiencePlan;
use App\Models\User;
use InvalidArgumentException;
use Modules\Exam\Services\QuantityTakeoffXlsxParser;

class UsExperiencePlanService
{
    public function __construct(
        private QuantityTakeoffXlsxParser $parser,
        private UsExperienceFileService $files,
    ) {}

    public function authorizeCourseAccess(Course $course, User $user): void
    {
        if (isAdmin()) {
            return;
        }

        if ($user->role === 'instructor' && (int) $user->instructor_id === (int) $course->instructor_id) {
            return;
        }

        abort(403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTrainer(Course $course): array
    {
        return UsExperiencePlan::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (UsExperiencePlan $plan) => $plan->toTrainerArray())
            ->values()
            ->all();
    }

    /**
     * @return list<array{group_name: string, group_description: string|null, plans: list<array{title: string}>}>
     */
    public function publicTease(Course $course): array
    {
        $plans = UsExperiencePlan::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (UsExperiencePlan $plan) => $plan->isReady());

        $groups = [];

        foreach ($plans as $plan) {
            $key = mb_strtolower(trim($plan->group_name));
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'group_name' => $plan->group_name,
                    'group_description' => $plan->group_description,
                    'plans' => [],
                ];
            }

            $groups[$key]['plans'][] = ['title' => $plan->title];
        }

        return array_values($groups);
    }

    public function create(Course $course, array $data): UsExperiencePlan
    {
        $maxSort = (int) UsExperiencePlan::query()->where('course_id', $course->id)->max('sort_order');

        return UsExperiencePlan::query()->create([
            'course_id' => $course->id,
            'group_name' => $data['group_name'],
            'group_description' => $data['group_description'] ?? null,
            'title' => $data['title'],
            'sort_order' => $maxSort + 1,
            'pass_mark' => $data['pass_mark'] ?? config('us_experience.default_pass_mark', 85),
            'max_attempts' => $data['max_attempts'] ?? config('us_experience.default_max_attempts', 10),
            'published' => (bool) ($data['published'] ?? false),
        ]);
    }

    public function update(UsExperiencePlan $plan, array $data): UsExperiencePlan
    {
        $plan->update([
            'group_name' => $data['group_name'] ?? $plan->group_name,
            'group_description' => array_key_exists('group_description', $data) ? $data['group_description'] : $plan->group_description,
            'title' => $data['title'] ?? $plan->title,
            'pass_mark' => $data['pass_mark'] ?? $plan->pass_mark,
            'max_attempts' => $data['max_attempts'] ?? $plan->max_attempts,
            'published' => array_key_exists('published', $data) ? (bool) $data['published'] : $plan->published,
        ]);

        return $plan->fresh();
    }

    public function delete(UsExperiencePlan $plan): void
    {
        $plan->delete();
    }

    public function move(UsExperiencePlan $plan, string $direction): void
    {
        $siblings = UsExperiencePlan::query()
            ->where('course_id', $plan->course_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (UsExperiencePlan $item) => (int) $item->id === (int) $plan->id);

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if (!isset($siblings[$swapWith])) {
            return;
        }

        $other = $siblings[$swapWith];
        $currentSort = $plan->sort_order;
        $plan->update(['sort_order' => $other->sort_order]);
        $other->update(['sort_order' => $currentSort]);
    }

    public function addDrawing(UsExperiencePlan $plan, string $fileUrl, string $fileName): UsExperiencePlan
    {
        $drawings = $plan->drawingsList();
        $drawings[] = [
            'file_url' => $fileUrl,
            'file_name' => $fileName,
        ];
        $plan->update(['drawings' => $drawings]);

        return $plan->fresh();
    }

    public function removeDrawing(UsExperiencePlan $plan, string $fileUrl): UsExperiencePlan
    {
        $drawings = array_values(array_filter(
            $plan->drawingsList(),
            fn (array $drawing) => ($drawing['file_url'] ?? '') !== $fileUrl
        ));
        $plan->update(['drawings' => $drawings]);

        return $plan->fresh();
    }

    public function importAnswerKey(UsExperiencePlan $plan, string $fileUrl, string $fileName): array
    {
        $resolved = $this->files->resolveLocalPath($fileUrl);

        try {
            $lineItems = $this->parser->parse($resolved['path']);
        } finally {
            $this->files->forgetTemporary($resolved);
        }

        $oldOverrides = collect($plan->line_items ?? [])
            ->mapWithKeys(fn (array $line) => [$line['key'] => $line['tolerance_override'] ?? null]);

        foreach ($lineItems as &$line) {
            if ($oldOverrides->has($line['key'])) {
                $line['tolerance_override'] = $oldOverrides[$line['key']];
            }
        }
        unset($line);

        $plan->update([
            'answer_key_file_url' => $fileUrl,
            'answer_key_file_name' => $fileName,
            'line_items' => $lineItems,
            'parsed_at' => now(),
        ]);

        return [
            'line_items' => $lineItems,
            'line_count' => count($lineItems),
        ];
    }

    public function saveStudentTemplate(UsExperiencePlan $plan, string $fileUrl, string $fileName): UsExperiencePlan
    {
        $plan->update([
            'blank_template_file_url' => $fileUrl,
            'blank_template_file_name' => $fileName,
        ]);

        return $plan->fresh();
    }

    public function saveTutorialVideo(UsExperiencePlan $plan, string $videoUrl, string $videoName): UsExperiencePlan
    {
        $plan->update([
            'tutorial_video_url' => $videoUrl,
            'tutorial_video_name' => $videoName,
        ]);

        return $plan->fresh();
    }

    /**
     * @param array<int, array{key: string, tolerance_override?: float|null}> $tolerances
     */
    public function saveLineTolerances(UsExperiencePlan $plan, array $tolerances): UsExperiencePlan
    {
        $lineItems = $plan->line_items ?? [];
        $toleranceMap = collect($tolerances)->keyBy('key');

        foreach ($lineItems as &$line) {
            if (!$toleranceMap->has($line['key'])) {
                continue;
            }

            $override = $toleranceMap[$line['key']]['tolerance_override'] ?? null;
            $line['tolerance_override'] = $override === null || $override === '' ? null : (float) $override;
        }
        unset($line);

        $plan->update(['line_items' => $lineItems]);

        return $plan->fresh();
    }

    /**
     * @return array{path: string, name: string, cleanup: list<string>}
     */
    public function buildStudentPack(UsExperiencePlan $plan): array
    {
        if (!$plan->isReady()) {
            throw new InvalidArgumentException('This plan is not ready to download.');
        }

        $zipPath = $this->files->makeTempFile('zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Unable to build the download pack.');
        }

        $cleanup = [$zipPath];
        $usedNames = [];

        try {
            foreach ($plan->drawingsList() as $index => $drawing) {
                $resolved = $this->files->resolveLocalPath($drawing['file_url']);
                if ($resolved['temporary']) {
                    $cleanup[] = $resolved['path'];
                }

                $name = $this->uniqueZipName($usedNames, $drawing['file_name'] ?? ('drawing-'.($index + 1).'.pdf'), 'drawings');
                $zip->addFile($resolved['path'], $name);
            }

            $template = $this->files->resolveLocalPath($plan->blank_template_file_url);
            if ($template['temporary']) {
                $cleanup[] = $template['path'];
            }
            $templateName = $this->uniqueZipName(
                $usedNames,
                $plan->blank_template_file_name ?: 'student-template.xlsx',
                'template'
            );
            $zip->addFile($template['path'], $templateName);
            $zip->close();
        } catch (\Throwable $exception) {
            @$zip->close();
            foreach ($cleanup as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            throw $exception;
        }

        foreach ($cleanup as $path) {
            if ($path !== $zipPath && is_file($path)) {
                @unlink($path);
            }
        }

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $plan->title) ?: 'plan';

        return [
            'path' => $zipPath,
            'name' => $slug.'-pack.zip',
        ];
    }

    /**
     * @param array<string, bool> $usedNames
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
