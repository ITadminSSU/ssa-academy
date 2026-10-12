<?php

use App\Http\Requests\ChunkInitiateRequest;

it('allows document uploads up to 5 GB so takeoff PDF plans can be large', function () {
    request()->merge(['filetype' => 'document']);

    $request = new ChunkInitiateRequest();
    $maxRule = collect($request->rules()['filesize'])->first(
        fn ($rule) => is_string($rule) && str_starts_with($rule, 'max:')
    );

    expect($maxRule)->toBe('max:5242880')
        ->and($request->messages()['filesize.max'])->toContain('5 GB');
});

it('keeps zip uploads at 256 MB', function () {
    request()->merge(['filetype' => 'zip']);

    $request = new ChunkInitiateRequest();
    $maxRule = collect($request->rules()['filesize'])->first(
        fn ($rule) => is_string($rule) && str_starts_with($rule, 'max:')
    );

    expect($maxRule)->toBe('max:262144');
});
