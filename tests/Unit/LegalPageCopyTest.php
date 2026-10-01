<?php

use App\Support\LegalPageCopy;

it('removes repeated cookie titles and keeps the sentences', function () {
    $html = '<h1 style="text-align: center;"><strong>Cookie policy</strong></h1><h2><strong>Cookie policy</strong></h2><ol><li><p>Cookies are small text files that can be used by websites to make a user\'s experience more efficient.</p></li></ol>';

    $stripped = LegalPageCopy::stripLeadingPageTitle($html, 'Cookie Policy');

    expect($stripped)->toStartWith('<ol>')
        ->and($stripped)->toContain('Cookies are small text files')
        ->and($stripped)->not->toContain('<h1')
        ->and($stripped)->not->toContain('<h2');
});

it('removes only a leading page title and leaves later headings', function () {
    $html = '<h1><strong>Privacy Policy</strong></h1><p>Welcome to SMARTSOURCING USA ACADEMY.</p><h2><strong>1. Information We Collect</strong></h2><p>We collect various types of information.</p>';

    $stripped = LegalPageCopy::stripLeadingPageTitle($html, 'Privacy Policy');

    expect($stripped)->toStartWith('<p>Welcome to SMARTSOURCING USA ACADEMY.</p>')
        ->and($stripped)->toContain('1. Information We Collect')
        ->and($stripped)->toContain('We collect various types of information.');
});

it('leaves a document heading that is not the page name', function () {
    $html = '<h2><strong>SMARTSOURCING USA ACADEMY</strong></h2><h2><strong>WEBSITE TERMS AND CONDITIONS</strong></h2><p>These Website Terms and Conditions ("Terms") govern your access.</p>';

    $stripped = LegalPageCopy::stripLeadingPageTitle($html, 'Terms and Conditions');

    expect($stripped)->toBe($html);
});

it('treats an nda heading as the page title', function () {
    $html = '<h1 style="text-align: center;"><strong>Non-Disclosure Agreement (NDA)</strong></h1><p>This Non-Disclosure Agreement ("Agreement") is entered into between SMARTSOURCING USA ACADEMY.</p>';

    $stripped = LegalPageCopy::stripLeadingPageTitle($html, 'Non-Disclosure Agreement');

    expect($stripped)->toStartWith('<p>This Non-Disclosure Agreement')
        ->and($stripped)->not->toContain('<h1');
});
