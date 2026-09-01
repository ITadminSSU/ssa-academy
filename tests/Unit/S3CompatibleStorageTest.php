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

test('extractObjectKey reads path-style R2 API urls used by chat attachments', function () {
    $url = 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/679/SL0001---Skill-Level-1---Plans.pdf';

    expect(S3CompatibleStorage::extractObjectKey($url))
        ->toBe('679/SL0001---Skill-Level-1---Plans.pdf');
});
