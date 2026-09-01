<?php

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;

it('signs private R2 chat attachments for the browser and stores unsigned urls', function () {
    config([
        'filesystems.disks.s3.bucket' => 'ssa-academy-files',
        'filesystems.disks.s3.endpoint' => 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com',
        'filesystems.disks.s3.url' => '',
        'filesystems.disks.s3.use_path_style_endpoint' => false,
        'filesystems.disks.s3.region' => 'us-east-1',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
    ]);

    $disk = Mockery::mock();
    $disk->shouldReceive('temporaryUrl')
        ->once()
        ->andReturn('https://signed.example/679/file.pdf?X-Amz-Signature=abc');

    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

    $message = new ChatMessage();
    $message->attachment = 'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/679/SL0001---Skill-Level-1---Plans.pdf';

    expect($message->getAttributes()['attachment'])->toBe(
        'https://662e2c7b71c8db5492dbba2e1f6e2a35.r2.cloudflarestorage.com/679/SL0001---Skill-Level-1---Plans.pdf'
    );
    expect($message->attachment)->toBe('https://signed.example/679/file.pdf?X-Amz-Signature=abc');
});
