<?php

declare(strict_types=1);

require __DIR__ . '/jersey-name-lib.php';

$sourceDir = $argv[1] ?? '';
$outputDir = $argv[2] ?? '';
$newName = $argv[3] ?? 'ROBBYROCKS';
$outputSuffix = $argv[4] ?? 'ROBBYROCKS';
$files = array_slice($argv, 5);

if ($sourceDir === '' || $outputDir === '') {
    fwrite(STDERR, "Usage: php replace-jersey-name.php <sourceDir> <outputDir> <newName> <outputSuffix> [files...]\n");
    exit(1);
}

if ($files === []) {
    $files = array_values(array_filter(scandir($sourceDir) ?: [], static function (string $file): bool {
        return (bool) preg_match('/\.(jpe?g|png)$/i', $file);
    }));
}

$fontBold = 'C:\\Windows\\Fonts\\arialbd.ttf';

if (!is_file($fontBold)) {
    fwrite(STDERR, "Font not found: {$fontBold}\n");
    exit(1);
}

if (is_dir($outputDir)) {
    foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*') ?: [] as $old) {
        if (is_file($old)) {
            unlink($old);
        }
    }
} elseif (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory: {$outputDir}\n");
    exit(1);
}

$processed = 0;
$skipped = [];

foreach ($files as $file) {
    $input = $sourceDir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($input)) {
        $skipped[] = "{$file} (missing)";
        continue;
    }

    $ext = strtolower(pathinfo($input, PATHINFO_EXTENSION));
    $output = $outputDir . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '_' . $outputSuffix . '.' . $ext;

    $img = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($input),
        'png' => @imagecreatefrompng($input),
        default => null,
    };

    if (!$img instanceof GdImage) {
        $skipped[] = "{$file} (unsupported or unreadable)";
        continue;
    }

    $width = imagesx($img);
    $height = imagesy($img);
    $region = resolveNameRegion($img, $width, $height, $file);

    if (!replaceName($img, $fontBold, $newName, $region)) {
        imagedestroy($img);
        $skipped[] = "{$file} (name area not detected)";
        continue;
    }

    $saved = match ($ext) {
        'jpg', 'jpeg' => imagejpeg($img, $output, 95),
        'png' => imagepng($img, $output, 6),
        default => false,
    };

    imagedestroy($img);

    if (!$saved) {
        $skipped[] = "{$file} (save failed)";
        continue;
    }

    $processed++;
    echo "Saved: {$output}\n";
}

echo PHP_EOL . "Processed: {$processed}" . PHP_EOL;
if ($skipped !== []) {
    echo "Skipped:\n - " . implode("\n - ", $skipped) . PHP_EOL;
}
