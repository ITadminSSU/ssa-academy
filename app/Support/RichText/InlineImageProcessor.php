<?php

namespace App\Support\RichText;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InlineImageProcessor
{
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function process(?string $html, string $directory = 'richtext-images'): ?string
    {
        if ($html === null || $html === '' || !str_contains($html, 'data:image')) {
            return $html;
        }

        return preg_replace_callback(
            '/src=(["\'])data:image\/([\w.+-]+);base64,([^"\']+)\1/i',
            function (array $matches) use ($directory) {
                $quote = $matches[1];
                $extension = $this->normalizeExtension($matches[2]);
                $binary = base64_decode($matches[3], true);

                if ($binary === false || strlen($binary) > self::MAX_IMAGE_BYTES) {
                    return $matches[0];
                }

                $path = trim($directory, '/') . '/' . Str::uuid() . '.' . $extension;

                Storage::disk('public')->put($path, $binary);

                $url = public_asset_url(Storage::disk('public')->url($path));

                return 'src=' . $quote . $url . $quote;
            },
            $html,
        ) ?? $html;
    }

    private function normalizeExtension(string $mimeSubtype): string
    {
        return match (strtolower($mimeSubtype)) {
            'jpeg', 'jpg' => 'jpg',
            'svg+xml' => 'svg',
            'webp' => 'webp',
            'gif' => 'gif',
            'png' => 'png',
            default => 'png',
        };
    }
}
