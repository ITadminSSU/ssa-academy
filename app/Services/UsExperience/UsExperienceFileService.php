<?php

namespace App\Services\UsExperience;

use App\Models\ChunkedUpload;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class UsExperienceFileService
{
    /**
     * @return array{path: string, temporary: bool}
     */
    public function resolveLocalPath(string $fileUrl): array
    {
        $upload = ChunkedUpload::query()->where('file_url', $fileUrl)->first();

        if ($upload) {
            $disk = Storage::disk($upload->disk);

            try {
                $localPath = $disk->path($upload->file_path);
                if (is_file($localPath)) {
                    return ['path' => $localPath, 'temporary' => false];
                }
            } catch (\Throwable) {
                // Cloud disks may not support path().
            }

            if ($disk->exists($upload->file_path)) {
                $extension = pathinfo($upload->file_path, PATHINFO_EXTENSION) ?: 'bin';
                $temporaryPath = $this->makeTempFile($extension);
                file_put_contents($temporaryPath, $disk->get($upload->file_path));

                return ['path' => $temporaryPath, 'temporary' => true];
            }
        }

        $publicPath = parse_url($fileUrl, PHP_URL_PATH);
        if (is_string($publicPath)) {
            $storagePath = public_path(ltrim($publicPath, '/'));
            if (is_file($storagePath)) {
                return ['path' => $storagePath, 'temporary' => false];
            }
        }

        throw new InvalidArgumentException('Could not locate the uploaded file.');
    }

    public function forgetTemporary(array $resolved): void
    {
        if (!empty($resolved['temporary']) && !empty($resolved['path']) && is_file($resolved['path'])) {
            @unlink($resolved['path']);
        }
    }

    public function makeTempFile(string $extension): string
    {
        $directory = storage_path('app/tmp');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.uniqid('usx_', true).'.'.$extension;
    }
}
