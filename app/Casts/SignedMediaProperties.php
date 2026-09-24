<?php

namespace App\Casts;

use App\Support\S3CompatibleStorage;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SignedMediaProperties implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return S3CompatibleStorage::mapArrayMediaUrls(is_array($decoded) ? $decoded : [], 'get');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $data = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);

        return json_encode(S3CompatibleStorage::mapArrayMediaUrls($data, 'set'));
    }
}
