<?php

use Database\Data\Sections\SsuHomeSections;

it('places the gap story section immediately after the home hero', function () {
    $sections = SsuHomeSections::getSections();
    $slugs = array_column($sections, 'slug');

    expect($slugs[0])->toBe('hero')
        ->and($slugs[1])->toBe('gap')
        ->and($slugs[2])->toBe('pillars');
});

it('adds the why-academy italic tagline on the pillars section', function () {
    $pillars = collect(SsuHomeSections::getSections())->firstWhere('slug', 'pillars');

    expect($pillars)->not->toBeNull()
        ->and($pillars['title'])->toBe('WHY SMARTSOURCING USA ACADEMY?')
        ->and($pillars['sub_title'])->toBe('We help you build skills that matter in the real world.')
        ->and($pillars['flags']['sub_title'])->toBeTrue();
});

it('keeps the circled email copy for the gap section', function () {
    $gap = collect(SsuHomeSections::getSections())->firstWhere('slug', 'gap');

    expect($gap)->not->toBeNull()
        ->and($gap['title'])->toBe('THE GAP IS WIDENING')
        ->and($gap['sub_title'])->toBe('EVERYONE HAS A TALENT.')
        ->and($gap['description'])->toContain('US-specific market expertise is rare')
        ->and($gap['properties']['accent_line'])->toBe('VERY FEW HAS U.S. EXPERIENCE.')
        ->and($gap['properties']['banner_name'])->toBe('SMARTSOURCING USA ACADEMY')
        ->and($gap['properties']['banner_action'])->toBe('CLOSES THAT GAP.')
        ->and($gap['properties']['closing_lead'])->toBe('100% focused on U.S. construction readiness.');
});
