<?php

use Database\Data\Sections\SsuHomeSections;

it('places the gap story section immediately after the home hero', function () {
    $sections = SsuHomeSections::getSections();
    $slugs = array_column($sections, 'slug');

    expect($slugs[0])->toBe('hero')
        ->and($slugs[1])->toBe('gap')
        ->and($slugs[2])->toBe('pillars')
        ->and($slugs[3])->toBe('inside_academy');
});

it('adds the why-academy italic tagline on the pillars section', function () {
    $pillars = collect(SsuHomeSections::getSections())->firstWhere('slug', 'pillars');

    expect($pillars)->not->toBeNull()
        ->and($pillars['title'])->toBe('WHY SMARTSOURCING USA ACADEMY?')
        ->and($pillars['sub_title'])->toBe('We help you build skills that matter in the real world.')
        ->and($pillars['flags']['sub_title'])->toBeTrue();
});

it('adds an admin-editable inside the academy instagram section after the pillars', function () {
    $section = collect(SsuHomeSections::getSections())->firstWhere('slug', 'inside_academy');

    expect($section)->not->toBeNull()
        ->and($section['title'])->toBe('INSIDE THE ACADEMY')
        ->and($section['sub_title'])->toBe('Explore the Learning, Stories, and Opportunities Within')
        ->and($section['flags']['title'])->toBeTrue()
        ->and($section['flags']['sub_title'])->toBeTrue()
        ->and($section['properties']['array'][0])->toHaveKeys(['image', 'link', 'views']);
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
