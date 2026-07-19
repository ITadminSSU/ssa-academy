<?php

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Exam\Http\Requests\ImportExamTakeoffAnswerKeyRequest;
use Modules\Exam\Http\Requests\SaveExamTakeoffTutorialRequest;
use Modules\Exam\Http\Requests\SaveExamTakeoffStudentTemplateRequest;
use Modules\Exam\Http\Requests\SaveExamTakeoffTolerancesRequest;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamQuantityTakeoffService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExamQuantityTakeoffController extends Controller
{
    public function __construct(
        private ExamQuantityTakeoffService $takeoffService,
    ) {}

    public function importAnswerKey(ImportExamTakeoffAnswerKeyRequest $request, Exam $exam): RedirectResponse
    {
        try {
            $result = $this->takeoffService->importAnswerKey(
                $exam,
                $request->validated('answer_key_file_url'),
                $request->validated('answer_key_file_name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Answer key validated and imported. ' . $result['line_count'] . ' quantity line(s) are ready for grading.'
        );
    }

    public function saveTutorial(SaveExamTakeoffTutorialRequest $request, Exam $exam): RedirectResponse
    {
        try {
            $this->takeoffService->saveTutorialVideo(
                $exam,
                $request->validated('tutorial_video_url'),
                $request->validated('tutorial_video_name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Tutorial video saved. Students will see it after they submit the exam.');
    }

    public function saveTolerances(SaveExamTakeoffTolerancesRequest $request, Exam $exam): RedirectResponse
    {
        try {
            $this->takeoffService->saveLineTolerances($exam, $request->validated('tolerances'));
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Per-line tolerances saved.');
    }

    public function saveStudentTemplate(SaveExamTakeoffStudentTemplateRequest $request, Exam $exam): RedirectResponse
    {
        try {
            $this->takeoffService->saveStudentTemplate(
                $exam,
                $request->validated('student_template_file_url'),
                $request->validated('student_template_file_name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Blank student template saved. Students can download it during the exam.');
    }

    public function downloadTemplate(Exam $exam): BinaryFileResponse|RedirectResponse
    {
        try {
            $template = $this->takeoffService->studentTemplateDownload($exam);
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return response()->download($template['path'], $template['name'])->deleteFileAfterSend(false);
    }
}
