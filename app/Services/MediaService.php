<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService extends BaseService
{
    public function getMediaByName(Model $model, string $name): string | null
    {
        $media = $model->getMedia('*', ['name' => $name])->first();

        return $media ? public_asset_url($media->getUrl()) : null;
    }

    public function addNewDeletePrev(Model $model, $image, ?string $name): string
    {
        if ($name) {
            $prevMedia = $model->getMedia('*', ['name' => $name])->first();
            if ($prevMedia) {
                $prevMedia->delete();
            }

            $newMedia = $this->addMediaSafely($model, $image, $name);
        } else {
            if ($model->hasMedia()) {
                $model->getMedia()->first()->delete();
            }

            $newMedia = $this->addMediaSafely($model, $image, null);
        }

        $model->unsetRelation('media');

        return public_asset_url($newMedia->getUrl());
    }

    public function addSingleFile(Model $model, $image, ?string $name)
    {
        $newMedia = $this->addMediaSafely($model, $image, $name);

        return public_asset_url($newMedia->getUrl());
    }

    /**
     * Copy uploads out of PHP's volatile /tmp before Spatie reads them.
     * Forge (and some hosts) can clear upload temp files mid-request, which
     * otherwise surfaces as: File "/tmp/phpXXXX" does not exist.
     */
    private function addMediaSafely(Model $model, $image, ?string $name): Media
    {
        $stablePath = null;

        try {
            [$sourcePath, $fileName] = $this->stabilizeUpload($image);
            $stablePath = $sourcePath;

            $adder = $model
                ->addMedia($sourcePath)
                ->usingFileName($fileName)
                ->preservingOriginal();

            if ($name) {
                $adder->withCustomProperties(['name' => $name]);
            }

            return $adder->toMediaCollection('default');
        } finally {
            if ($stablePath && is_file($stablePath) && str_starts_with($stablePath, storage_path('app/tmp-uploads'))) {
                @unlink($stablePath);
            }
        }
    }

    /**
     * @return array{0: string, 1: string} [absolutePath, fileName]
     */
    private function stabilizeUpload(mixed $image): array
    {
        if ($image instanceof UploadedFile) {
            if (! $image->isValid()) {
                throw new \RuntimeException($image->getErrorMessage() ?: 'The uploaded photo is invalid.');
            }

            $realPath = $image->getRealPath();
            if (! $realPath || ! is_file($realPath)) {
                throw new \RuntimeException('The uploaded photo could not be read from temporary storage. Please try again.');
            }

            $extension = $image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg';
            $fileName = $image->getClientOriginalName() ?: ('profile-photo.'.$extension);
            $stablePath = storage_path('app/tmp-uploads/'.Str::uuid().'_'.basename($fileName));

            File::ensureDirectoryExists(dirname($stablePath));
            if (! @copy($realPath, $stablePath)) {
                throw new \RuntimeException('Failed to store the uploaded photo. Please try again.');
            }

            return [$stablePath, $fileName];
        }

        if (is_string($image) && is_file($image)) {
            return [$image, basename($image)];
        }

        throw new \InvalidArgumentException('Invalid photo upload.');
    }
}
