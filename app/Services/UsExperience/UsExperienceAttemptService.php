<?php

namespace App\Services\UsExperience;

use App\Models\Course\UsExperienceAttempt;
use App\Models\Course\UsExperiencePlan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Modules\Exam\Services\QuantityTakeoffGradingService;
use Modules\Exam\Services\QuantityTakeoffXlsxParser;

class UsExperienceAttemptService
{
    public function __construct(
        private QuantityTakeoffXlsxParser $parser,
        private QuantityTakeoffGradingService $grader,
        private UsExperienceFileService $files,
    ) {}

    public function submit(
        UsExperiencePlan $plan,
        User $user,
        string $takeoffPdfUrl,
        string $takeoffPdfName,
        string $boqXlsxUrl,
        string $boqXlsxName,
    ): UsExperienceAttempt {
        $lineItems = $plan->answerKeyLines();

        if ($lineItems === []) {
            throw new InvalidArgumentException('This plan does not have an imported answer key yet.');
        }

        $resolved = $this->files->resolveLocalPath($boqXlsxUrl);

        try {
            $studentLines = $this->parser->parse($resolved['path']);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                'Could not read the submitted Excel BOQ. Use the Estimator Notes template and fill Quantity Summary. '.$exception->getMessage()
            );
        } finally {
            $this->files->forgetTemporary($resolved);
        }

        $quantities = [];
        foreach ($studentLines as $line) {
            $quantities[$line['key']] = $line['expected_qty'];
        }

        $result = $this->grader->grade(
            $lineItems,
            ['quantities' => $quantities],
            (float) config('us_experience.default_total_marks', 100),
            defaultPercentTolerance: (float) config('us_experience.default_tolerance_percent', 2),
        );

        $passed = $result['lines_percent'] >= (float) $plan->pass_mark;
        $attemptNumber = (int) UsExperienceAttempt::query()
            ->where('us_experience_plan_id', $plan->id)
            ->where('user_id', $user->id)
            ->max('attempt_number') + 1;

        return UsExperienceAttempt::query()->create([
            'us_experience_plan_id' => $plan->id,
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'takeoff_pdf_url' => $takeoffPdfUrl,
            'takeoff_pdf_name' => $takeoffPdfName,
            'boq_xlsx_url' => $boqXlsxUrl,
            'boq_xlsx_name' => $boqXlsxName,
            'answer_data' => ['quantities' => $quantities],
            'marks_obtained' => $result['marks_obtained'],
            'lines_correct' => $result['lines_correct'],
            'lines_total' => $result['lines_total'],
            'lines_percent' => $result['lines_percent'],
            'grading_breakdown' => $result['grading_breakdown'],
            'status' => $passed ? UsExperienceAttempt::STATUS_PASSED : UsExperienceAttempt::STATUS_FAILED,
            'submitted_at' => now(),
        ]);
    }

    public function paginateForTrainer(UsExperiencePlan $plan, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = $perPage > 0 ? min($perPage, 50) : 20;

        return UsExperienceAttempt::query()
            ->with(['user:id,name,email'])
            ->where('us_experience_plan_id', $plan->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($user) use ($search) {
                    $user->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (UsExperienceAttempt $attempt) => $attempt->toTrainerArray());
    }

    public function saveTrainerFeedback(UsExperienceAttempt $attempt, ?string $feedback): UsExperienceAttempt
    {
        $attempt->update([
            'trainer_feedback' => filled($feedback) ? trim($feedback) : null,
        ]);

        return $attempt->fresh(['user']) ?? $attempt;
    }
}
