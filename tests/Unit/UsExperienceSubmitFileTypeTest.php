<?php

use App\Http\Requests\UsExperience\SubmitUsExperienceAttemptRequest;
use Illuminate\Support\Facades\Validator;

function takeoffSubmitPayload(array $overrides = []): array
{
    return array_merge([
        'takeoff_pdf_url' => 'https://example.test/takeoff.pdf',
        'takeoff_pdf_name' => 'Skills Building 1.pdf',
        'boq_xlsx_url' => 'https://example.test/boq.xlsx',
        'boq_xlsx_name' => 'Quantity Report.xlsx',
    ], $overrides);
}

it('rejects a takeoff upload that is not a pdf', function () {
    $request = new SubmitUsExperienceAttemptRequest();
    $validator = Validator::make(takeoffSubmitPayload([
        'takeoff_pdf_name' => 'SL0001 - Skill Level 1 - Quantity Report.xlsx',
    ]), $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('takeoff_pdf_name'))->toBe('The takeoff file must be a PDF.');
});

it('rejects a boq upload that is not xlsx', function () {
    $request = new SubmitUsExperienceAttemptRequest();
    $validator = Validator::make(takeoffSubmitPayload([
        'boq_xlsx_name' => 'marked-up-plans.pdf',
    ]), $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('boq_xlsx_name'))->toBe('The Excel BOQ must be an .xlsx file.');
});

it('accepts a pdf takeoff and xlsx boq', function () {
    $request = new SubmitUsExperienceAttemptRequest();
    $validator = Validator::make(takeoffSubmitPayload(), $request->rules(), $request->messages());

    expect($validator->fails())->toBeFalse();
});
