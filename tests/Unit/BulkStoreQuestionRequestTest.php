<?php

use App\Http\Requests\BulkStoreQuestionRequest;
use Illuminate\Support\Facades\Validator;

function bulkQuestionPayload(string $title): array
{
    return [
        'section_quiz_id' => 65,
        'questions' => [[
            'title' => $title,
            'type' => 'multiple',
            'options' => ['A. Pre-cut studs', 'B. LVL', 'C. Common lumber', 'D. Furring Strips'],
            'answer' => ['B. LVL'],
        ]],
    ];
}

it('allows rich-text quiz titles longer than 255 characters', function () {
    $title = '<p><span>'.str_repeat('An estimator needs a longer-span framing product. ', 8).'Which product from the lesson is the most appropriate choice?</span></p>';

    expect(strlen($title))->toBeGreaterThan(255);

    $request = BulkStoreQuestionRequest::create('/quiz/questions/bulk', 'POST', bulkQuestionPayload($title));
    $rules = $request->rules();
    $rules['section_quiz_id'] = 'required';

    $validator = Validator::make($request->all(), $rules, $request->messages());

    expect($validator->fails())->toBeFalse();
});
