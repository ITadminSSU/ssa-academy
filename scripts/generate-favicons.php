<?php

declare(strict_types=1);

$source = $argv[1] ?? '';

if ($source === '') {
    fwrite(STDERR, "Usage: php scripts/generate-favicons.php <source-image>\n");
    exit(1);
}

if (!is_file($source)) {
    fwrite(STDERR, "Source image not found: {$source}\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    App\Support\FaviconFiles::writeFromImageFile($source);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

foreach (App\Support\FaviconFiles::pngTargets() as $path => $size) {
    echo "Wrote {$path} ({$size}x{$size})".PHP_EOL;
}

echo 'Wrote '.public_path('favicon.ico').PHP_EOL;

$updated = 0;

foreach (App\Models\Setting::query()->where('type', 'system')->get() as $setting) {
    $fields = is_array($setting->fields) ? $setting->fields : [];
    $fields['favicon'] = '/favicon.png';
    $setting->update(['fields' => $fields]);
    $updated++;
}

echo "Updated {$updated} system setting row(s) to /favicon.png.".PHP_EOL;
echo "Done.".PHP_EOL;
