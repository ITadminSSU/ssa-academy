<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStoreQuestionRequest;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Requests\UsExperience\SaveUsExperienceTolerancesRequest;
use App\Http\Requests\UsExperience\SaveUsExperienceUploadedFileRequest;
use App\Models\Course\QuizQuestion;
use App\Services\Course\QuizQuestionService;
use App\Services\Course\QuizTakeoffService;
use App\Support\S3CompatibleStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class QuestionController extends Controller
{
    public function __construct(
        private QuizQuestionService $questionService,
        private QuizTakeoffService $takeoff,
    ) {}

    public function store(StoreQuestionRequest $request)
    {
        $this->questionService->createQuestion($request->validated());

        return back()->with('success', 'Question has been added.');
    }

    public function bulkStore(BulkStoreQuestionRequest $request)
    {
        $count = $this->questionService->bulkCreateQuestions(
            $request->input('section_quiz_id'),
            $request->input('questions', []),
        );

        return back()->with('success', $count . ' question' . ($count === 1 ? '' : 's') . ' added.');
    }

    public function update(UpdateQuestionRequest $request, $id)
    {
        $this->questionService->updateQuestion($request->validated(), $id);

        return back()->with('success', 'Question has been updated.');
    }

    public function delete($id)
    {
        $this->questionService->deleteQuestion($id);

        return back()->with('success', 'Question has been deleted.');
    }

    public function sort(Request $request)
    {
        $this->questionService->sortQuestions($request->sortedData);

        return back()->with('success', 'Sections sorted successfully');
    }

    public function addTakeoffDrawing(SaveUsExperienceUploadedFileRequest $request, $id)
    {
        $this->takeoff->addDrawing(
            $this->takeoffQuestion($id),
            $request->validated('file_url'),
            $request->validated('file_name'),
        );

        return back()->with('success', 'Reference drawing added.');
    }

    public function removeTakeoffDrawing(Request $request, $id)
    {
        $fileUrl = $request->validate([
            'file_url' => 'required|string|max:2048',
        ])['file_url'];
        $this->takeoff->removeDrawing($this->takeoffQuestion($id), $fileUrl);

        return back()->with('success', 'Reference drawing removed.');
    }

    public function importTakeoffAnswerKey(SaveUsExperienceUploadedFileRequest $request, $id)
    {
        try {
            $result = $this->takeoff->importAnswerKey(
                $this->takeoffQuestion($id),
                $request->validated('file_url'),
                $request->validated('file_name'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Answer key validated and imported. '.$result['line_count'].' quantity line(s) are ready. Default tolerance is 2%; edit any line below if needed.'
        );
    }

    public function saveTakeoffTutorial(SaveUsExperienceUploadedFileRequest $request, $id)
    {
        $this->takeoff->saveTutorialVideo(
            $this->takeoffQuestion($id),
            $request->validated('file_url'),
            $request->validated('file_name'),
        );

        return back()->with('success', 'Tutorial video saved. Students see it after they submit.');
    }

    public function saveTakeoffTolerances(SaveUsExperienceTolerancesRequest $request, $id)
    {
        $this->takeoff->saveLineTolerances($this->takeoffQuestion($id), $request->validated('tolerances'));

        return back()->with('success', 'Per-line tolerances saved.');
    }

    public function viewTakeoffAnswerKey($id): RedirectResponse
    {
        $question = $this->takeoffQuestion($id);
        $options = $question->decodedOptions();

        return $this->redirectToStoredFile(
            $options['answer_key_file_url'] ?? null,
            $options['answer_key_file_name'] ?? 'answer-key.xlsx',
        );
    }

    public function viewTakeoffDrawing(Request $request, $id): RedirectResponse
    {
        $question = $this->takeoffQuestion($id);
        $fileUrl = $request->validate([
            'file_url' => 'required|string|max:2048',
        ])['file_url'];

        $match = collect($this->takeoff->drawingsFromOptions($question->decodedOptions()))
            ->first(fn (array $drawing) => ($drawing['file_url'] ?? '') === $fileUrl);

        if (! $match) {
            abort(404);
        }

        return $this->redirectToStoredFile($match['file_url'], $match['file_name'] ?? 'drawing.pdf');
    }

    private function redirectToStoredFile(?string $url, ?string $downloadName = null): RedirectResponse
    {
        if (! filled($url)) {
            abort(404);
        }

        $browserUrl = S3CompatibleStorage::browserUrl($url, $downloadName);

        if (! filled($browserUrl)) {
            abort(404);
        }

        return redirect()->away($browserUrl);
    }

    private function takeoffQuestion($id): QuizQuestion
    {
        $question = QuizQuestion::findOrFail($id);
        $this->takeoff->assertTakeoff($question);

        return $question;
    }
}
