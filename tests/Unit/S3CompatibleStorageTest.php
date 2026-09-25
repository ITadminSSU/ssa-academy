<?php

use App\Support\S3CompatibleStorage;

beforeEach(function () {
    config([
        'filesystems.disks.s3.bucket' => 'ssa-academy-files',
        'filesystems.disks.s3.endpoint' => 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com',
        'filesystems.disks.s3.url' => '',
        'filesystems.disks.s3.use_path_style_endpoint' => false,
    ]);
});

test('extractObjectKey decodes percent-encoded parentheses from stored R2 urls', function () {
    $url = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/493/Drywall-%28Basic%29-Thumbnail.jpeg';

    expect(S3CompatibleStorage::extractObjectKey($url))
        ->toBe('493/Drywall-(Basic)-Thumbnail.jpeg');
});

test('extractObjectKey recovers from double-encoded object keys', function () {
    $url = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/493/Drywall-%2528Basic%2529-Thumbnail.jpeg';

    expect(S3CompatibleStorage::extractObjectKey($url))
        ->toBe('493/Drywall-(Basic)-Thumbnail.jpeg');
});

test('decodeObjectKey leaves plain keys unchanged', function () {
    expect(S3CompatibleStorage::decodeObjectKey('489/Lumber-Thumbnail.jpeg'))
        ->toBe('489/Lumber-Thumbnail.jpeg');
});

test('videoMimeForKey maps playback extensions used by walkthrough uploads', function () {
    expect(S3CompatibleStorage::videoMimeForKey('lessons/abc.mp4'))->toBe('video/mp4');
    expect(S3CompatibleStorage::videoMimeForKey('lessons/abc.webm'))->toBe('video/webm');
    expect(S3CompatibleStorage::videoMimeForKey('lessons/abc.xlsx'))->toBeNull();
});

test('isRelativePublicDiskPath ignores urls and rooted paths', function () {
    expect(S3CompatibleStorage::isRelativePublicDiskPath('/storage/logo.png'))->toBeFalse();
    expect(S3CompatibleStorage::isRelativePublicDiskPath('https://example.com/logo.png'))->toBeFalse();
    expect(S3CompatibleStorage::isRelativePublicDiskPath(''))->toBeFalse();
});

test('extractObjectKey reads path-style R2 API urls used by chat attachments', function () {
    $url = 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/679/SL0001---Skill-Level-1---Plans.pdf';

    expect(S3CompatibleStorage::extractObjectKey($url))
        ->toBe('679/SL0001---Skill-Level-1---Plans.pdf');
});

test('temporaryObjectUrl adds attachment content disposition for downloads', function () {
    config([
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $url = S3CompatibleStorage::temporaryObjectUrl('12/resume.pdf', downloadName: 'resume.pdf');

    expect(urldecode($url))
        ->toContain('response-content-disposition=attachment; filename="resume.pdf"')
        ->and($url)->toContain('X-Amz-Signature=');
});

test('mapArrayMediaUrls signs nested page-section image fields and leaves instagram links alone', function () {
    config([
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $stored = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/academy.jpg';
    $instagram = 'https://www.instagram.com/reel/abc';

    $signed = S3CompatibleStorage::mapArrayMediaUrls([
        'array' => [
            ['image' => $stored, 'link' => $instagram, 'views' => '906'],
            ['image' => '', 'link' => '', 'views' => ''],
        ],
    ], 'get');

    expect($signed['array'][0]['image'])->toContain('X-Amz-Signature=')
        ->and($signed['array'][0]['link'])->toBe($instagram)
        ->and($signed['array'][0]['views'])->toBe('906')
        ->and($signed['array'][1]['image'])->toBe('');
});

test('page section properties persist unsigned r2 urls and sign them on read', function () {
    config([
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $stored = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/academy.jpg';

    $section = new \App\Models\PageSection;
    $section->properties = [
        'array' => [
            ['image' => $stored, 'link' => 'https://www.instagram.com/p/abc', 'views' => '1,611'],
        ],
    ];

    $raw = $section->getAttributes()['properties'];

    expect($raw)->not->toContain('X-Amz-Signature')
        ->and($section->properties['array'][0]['image'])->toContain('X-Amz-Signature=')
        ->and($section->properties['array'][0]['link'])->toBe('https://www.instagram.com/p/abc');
});

test('sameStoredObject matches a signed r2 url to the unsigned stored object url', function () {
    config([
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $stored = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/BL001 - Plans.pdf';
    $signed = S3CompatibleStorage::temporaryObjectUrl('12/BL001 - Plans.pdf');
    $other = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/other.pdf';

    expect(S3CompatibleStorage::sameStoredObject($stored, $signed))->toBeTrue()
        ->and(S3CompatibleStorage::sameStoredObject($stored, $other))->toBeFalse()
        ->and(S3CompatibleStorage::sameStoredObject('/storage/plans.pdf', 'https://smartsourcingacademy.com/storage/plans.pdf'))->toBeTrue();
});

test('rejectFirstMatchingMediaUrl removes only the signed drawing and leaves the rest', function () {
    config([
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $keep = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/keep.pdf';
    $remove = 'https://ssa-academy-files.662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/12/BL001 - Plans.pdf';
    $signed = S3CompatibleStorage::temporaryObjectUrl('12/BL001 - Plans.pdf');

    $kept = S3CompatibleStorage::rejectFirstMatchingMediaUrl([
        ['file_url' => $keep, 'file_name' => 'keep.pdf'],
        ['file_url' => $remove, 'file_name' => 'BL001 - Plans.pdf'],
    ], $signed);

    expect($kept)->toHaveCount(1)
        ->and($kept[0]['file_name'])->toBe('keep.pdf');
});
