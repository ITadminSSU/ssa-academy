<?php

use App\Models\Course\AssignmentSubmission;
use App\Models\Course\LessonActivitySubmission;

beforeEach(function () {
    config([
        'filesystems.disks.s3.bucket' => 'ssa-academy-files',
        'filesystems.disks.s3.endpoint' => 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com',
        'filesystems.disks.s3.url' => '',
        'filesystems.disks.s3.use_path_style_endpoint' => false,
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);
});

it('signs private R2 activity submissions for the browser and stores unsigned urls', function () {
    $raw = 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/lessons/f5d9704b-27cb-40e6-a222-6b9e6dc1ae7b.xlsx';

    $submission = new LessonActivitySubmission();
    $submission->attachment_type = 'file';
    $submission->attachment_path = $raw;

    expect($submission->getAttributes()['attachment_path'])->toBe($raw);
    expect($submission->attachment_path)
        ->toContain('f5d9704b-27cb-40e6-a222-6b9e6dc1ae7b.xlsx')
        ->toContain('X-Amz-Signature=');
});

it('leaves external activity submission urls unsigned', function () {
    $url = 'https://docs.google.com/document/d/abc123';

    $submission = new LessonActivitySubmission();
    $submission->attachment_type = 'url';
    $submission->attachment_path = $url;

    expect($submission->getAttributes()['attachment_path'])->toBe($url);
    expect($submission->attachment_path)->toBe($url);
});

it('signs private R2 assignment submissions for the browser and stores unsigned urls', function () {
    $raw = 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/lessons/f5d9704b-27cb-40e6-a222-6b9e6dc1ae7b.xlsx';

    $submission = new AssignmentSubmission();
    $submission->attachment_type = 'file';
    $submission->attachment_path = $raw;

    expect($submission->getAttributes()['attachment_path'])->toBe($raw);
    expect($submission->attachment_path)
        ->toContain('f5d9704b-27cb-40e6-a222-6b9e6dc1ae7b.xlsx')
        ->toContain('X-Amz-Signature=');
});
