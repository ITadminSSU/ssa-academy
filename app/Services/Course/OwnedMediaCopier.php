<?php

namespace App\Services\Course;

use App\Models\ChunkedUpload;
use App\Support\S3CompatibleStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a stored course file onto a new key so each course owns its bytes.
 * External links (YouTube, Vimeo, other sites) stay as references.
 */
class OwnedMediaCopier
{
    /**
     * @var list<array{disk: string, key: string}>
     */
    private array $created = [];

    public function resetTracking(): void
    {
        $this->created = [];
    }

    public function rollback(): void
    {
        foreach (array_reverse($this->created) as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['key']);
                $directory = dirname($file['key']);
                if ($directory !== '.' && $directory !== '') {
                    Storage::disk($file['disk'])->deleteDirectory($directory);
                }
            } catch (\Throwable) {
                // The duplicate already failed. Leave storage cleanup best-effort.
            }
        }

        $this->created = [];
    }

    public function copyValue(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (S3CompatibleStorage::isExternalEmbedUrl($value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        $upload = ChunkedUpload::query()->where('file_url', $value)->first();

        if ($upload && filled($upload->key)) {
            return $this->copyStoredObject((string) $upload->disk, (string) $upload->key, $upload);
        }

        if (S3CompatibleStorage::isRelativePublicDiskPath($value)) {
            return $this->copyStoredObject('public', $value, null);
        }

        $publicRelative = $this->publicDiskRelativePath($value);

        if ($publicRelative !== null && Storage::disk('public')->exists($publicRelative)) {
            return $this->copyStoredObject('public', $publicRelative, null);
        }

        $objectKey = S3CompatibleStorage::extractObjectKey($value);

        if ($objectKey && $this->isOurObjectUrl($value) && $this->diskHas('s3', $objectKey)) {
            return $this->copyStoredObject('s3', $objectKey, null);
        }

        if ($this->looksLikeOurMissingFile($value, $publicRelative, $objectKey)) {
            throw new CourseDuplicateException('A course file could not be copied, so the course was not duplicated.');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    public function rewriteTree(array $value): array
    {
        $copy = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isMediaKey($key) && is_string($item)) {
                $copy[$key] = $this->copyValue($item);

                continue;
            }

            $copy[$key] = is_array($item) ? $this->rewriteTree($item) : $item;
        }

        return $copy;
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    public function copyUrlList(array $urls): array
    {
        $copied = [];

        foreach ($urls as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $next = $this->copyValue($url);

            if (is_string($next) && $next !== '') {
                $copied[] = $next;
            }
        }

        return $copied;
    }

    private function isMediaKey(string $key): bool
    {
        return in_array($key, [
            'file_url',
            'pdf_url',
            'tutorial_video_url',
            'hero_photo',
            'video_url',
            'image',
            'photo',
            'src',
            'resource',
        ], true);
    }

    private function copyStoredObject(string $disk, string $key, ?ChunkedUpload $upload): string
    {
        $disk = $disk !== '' ? $disk : 'public';

        if (! $this->diskHas($disk, $key)) {
            throw new CourseDuplicateException('A course file could not be copied, so the course was not duplicated.');
        }

        $extension = pathinfo($key, PATHINFO_EXTENSION);
        $filename = 'file'.($extension !== '' ? '.'.$extension : '');
        $newKey = 'course-copies/'.Str::uuid().'/'.$filename;

        if (! Storage::disk($disk)->copy($key, $newKey)) {
            throw new CourseDuplicateException('A course file could not be copied, so the course was not duplicated.');
        }

        $this->created[] = ['disk' => $disk, 'key' => $newKey];

        if ($disk === 's3') {
            $url = S3CompatibleStorage::objectFileUrl($newKey);
        } else {
            $url = '/storage/'.$newKey;
        }

        if ($upload) {
            ChunkedUpload::query()->create([
                'user_id' => $upload->user_id,
                'filename' => $filename,
                'original_filename' => $upload->original_filename ?: $filename,
                'file_path' => $newKey,
                'file_url' => $url,
                'disk' => $disk,
                'mime_type' => $upload->mime_type,
                'size' => $upload->size,
                'upload_id' => (string) Str::uuid(),
                'key' => $newKey,
                'status' => 'completed',
                'chunks_completed' => 1,
                'total_chunks' => 1,
                'metadata' => ['copied_from' => $upload->id],
            ]);
        }

        return $url;
    }

    private function diskHas(string $disk, string $key): bool
    {
        try {
            return Storage::disk($disk)->exists($key);
        } catch (\Throwable) {
            return false;
        }
    }

    private function publicDiskRelativePath(string $value): ?string
    {
        $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
        $marker = '/storage/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return null;
        }

        $relative = ltrim(substr($path, $position + strlen($marker)), '/');

        return $relative !== '' ? $relative : null;
    }

    private function isOurObjectUrl(string $value): bool
    {
        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $endpoint = strtolower((string) parse_url((string) config('filesystems.disks.s3.endpoint'), PHP_URL_HOST));
        $publicHost = strtolower((string) parse_url((string) config('filesystems.disks.s3.url'), PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        return ($endpoint !== '' && ($host === $endpoint || str_ends_with($host, '.'.$endpoint)))
            || ($publicHost !== '' && $host === $publicHost)
            || str_contains($host, 'r2.cloudflarestorage.com')
            || str_contains($host, '.amazonaws.com');
    }

    private function looksLikeOurMissingFile(string $value, ?string $publicRelative, ?string $objectKey): bool
    {
        if ($publicRelative !== null) {
            return true;
        }

        if ($objectKey && $this->isOurObjectUrl($value)) {
            return true;
        }

        return ! str_starts_with($value, 'http://')
            && ! str_starts_with($value, 'https://')
            && ! str_starts_with($value, '/');
    }
}
