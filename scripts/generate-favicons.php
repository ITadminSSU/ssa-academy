<?php

declare(strict_types=1);

$source = $argv[1] ?? '';
$projectRoot = dirname(__DIR__);

if ($source === '') {
    fwrite(STDERR, "Usage: php scripts/generate-favicons.php <source-image>\n");
    exit(1);
}

if (!is_file($source)) {
    fwrite(STDERR, "Source image not found: {$source}\n");
    exit(1);
}

$targets = [
    'public/favicon.png' => 512,
    'public/favicon-32x32.png' => 32,
    'public/favicon-16x16.png' => 16,
    'public/apple-touch-icon.png' => 180,
    'public/assets/branding/favicon-ssa.png' => 512,
];

$imageInfo = getimagesize($source);
if ($imageInfo === false) {
    fwrite(STDERR, "Unable to read image: {$source}\n");
    exit(1);
}

[$width, $height, $type] = $imageInfo;

$sourceImage = match ($type) {
    IMAGETYPE_PNG => imagecreatefrompng($source),
    IMAGETYPE_JPEG => imagecreatefromjpeg($source),
    IMAGETYPE_WEBP => imagecreatefromwebp($source),
    default => null,
};

if (!$sourceImage) {
    fwrite(STDERR, "Unsupported image type.\n");
    exit(1);
}

imagealphablending($sourceImage, true);
imagesavealpha($sourceImage, true);

foreach ($targets as $relativePath => $size) {
    $destination = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($destination);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        fwrite(STDERR, "Failed to create directory: {$directory}\n");
        exit(1);
    }

    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

    $scale = min($size / $width, $size / $height);
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

    imagepng($canvas, $destination);
    imagedestroy($canvas);

    echo "Wrote {$relativePath} ({$size}x{$size})\n";
}

imagedestroy($sourceImage);

echo "Done.\n";
