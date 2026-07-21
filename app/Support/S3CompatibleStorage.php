<?php

namespace App\Support;

use Aws\S3\S3Client;

class S3CompatibleStorage
{
    public static function isR2Endpoint(?string $endpoint): bool
    {
        if (!$endpoint) {
            return false;
        }

        return str_contains(strtolower($endpoint), 'r2.cloudflarestorage.com');
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $config = [
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'region' => config('filesystems.disks.s3.region') ?: 'auto',
            'version' => 'latest',
        ];

        $endpoint = config('filesystems.disks.s3.endpoint');

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);
        }

        return $config;
    }

    public static function makeClient(): S3Client
    {
        return new S3Client(static::clientConfig());
    }

    public static function objectFileUrl(string $key): string
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $endpoint = config('filesystems.disks.s3.endpoint');

        if ($endpoint) {
            $base = rtrim((string) $endpoint, '/');

            if (config('filesystems.disks.s3.use_path_style_endpoint')) {
                return "{$base}/{$bucket}/{$key}";
            }

            return "{$base}/{$key}";
        }

        $region = config('filesystems.disks.s3.region');

        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}";
    }
}
