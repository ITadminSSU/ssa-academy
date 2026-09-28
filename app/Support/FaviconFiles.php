<?php

namespace App\Support;

class FaviconFiles
{
    /**
     * @return array<string, int>
     */
    public static function pngTargets(): array
    {
        return [
            public_path('favicon.png') => 512,
            public_path('favicon-32x32.png') => 32,
            public_path('favicon-16x16.png') => 16,
            public_path('apple-touch-icon.png') => 180,
            public_path('assets/branding/favicon-ssa.png') => 512,
        ];
    }

    public static function writeFromImageFile(string $sourcePath): void
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw new \RuntimeException('That file is not a readable image. Please upload a PNG.');
        }

        [$width, $height, $type] = $info;
        $sourceImage = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$sourceImage) {
            throw new \RuntimeException('That image type is not supported. Please upload a PNG.');
        }

        imagealphablending($sourceImage, true);
        imagesavealpha($sourceImage, true);

        foreach (self::pngTargets() as $destination => $size) {
            $directory = dirname($destination);

            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                imagedestroy($sourceImage);
                throw new \RuntimeException('Could not save the favicon files on the server.');
            }

            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

            $scale = min($size / max($width, 1), $size / max($height, 1));
            $targetWidth = (int) round($width * $scale);
            $targetHeight = (int) round($height * $scale);
            $offsetX = (int) round(($size - $targetWidth) / 2);
            $offsetY = (int) round(($size - $targetHeight) / 2);

            imagecopyresampled(
                $canvas,
                $sourceImage,
                $offsetX,
                $offsetY,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height
            );

            if (!@imagepng($canvas, $destination)) {
                imagedestroy($canvas);
                imagedestroy($sourceImage);
                throw new \RuntimeException('Could not save the favicon files on the server.');
            }

            imagedestroy($canvas);
        }

        imagedestroy($sourceImage);

        self::writeIcoFromPngs(
            public_path('favicon.ico'),
            [
                public_path('favicon-16x16.png'),
                public_path('favicon-32x32.png'),
            ],
        );
    }

    /**
     * @param  list<string>  $pngPaths
     */
    public static function writeIcoFromPngs(string $destination, array $pngPaths): void
    {
        $images = [];

        foreach ($pngPaths as $pngPath) {
            if (!is_file($pngPath)) {
                continue;
            }

            $data = file_get_contents($pngPath);
            $info = @getimagesize($pngPath);

            if ($data === false || $info === false) {
                continue;
            }

            $images[] = [
                'width' => $info[0] >= 256 ? 0 : $info[0],
                'height' => $info[1] >= 256 ? 0 : $info[1],
                'data' => $data,
            ];
        }

        if ($images === []) {
            throw new \RuntimeException('Could not save the favicon files on the server.');
        }

        $count = count($images);
        $offset = 6 + (16 * $count);
        $directory = pack('vvv', 0, 1, $count);
        $entries = '';
        $payload = '';

        foreach ($images as $image) {
            $size = strlen($image['data']);
            $entries .= pack(
                'CCCCvvVV',
                $image['width'],
                $image['height'],
                0,
                0,
                1,
                32,
                $size,
                $offset,
            );
            $offset += $size;
            $payload .= $image['data'];
        }

        if (@file_put_contents($destination, $directory.$entries.$payload) === false) {
            throw new \RuntimeException('Could not save the favicon files on the server.');
        }
    }
}
