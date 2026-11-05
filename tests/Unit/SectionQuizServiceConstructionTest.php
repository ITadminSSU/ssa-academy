<?php

use App\Services\Course\SectionQuizService;

it('builds the quiz service when its parent has no constructor', function () {
    expect(fn () => app(SectionQuizService::class))->not->toThrow(Error::class);
    expect(app(SectionQuizService::class))->toBeInstanceOf(SectionQuizService::class);
});
