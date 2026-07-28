<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$skip = ['vendor', 'node_modules', 'storage', '.git', 'public/build'];
$replacements = [
    ['Smart Sourcing Academy', 'SMARTSOURCING USA ACADEMY'],
    ['SMART SOURCING ACADEMY', 'SMARTSOURCING USA ACADEMY'],
];
$extensions = ['php', 'tsx', 'ts', 'md', 'json', 'css'];
$filenames = ['.env.example', '.env.hostinger.example', 'docker.env'];
$changed = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();

    foreach ($skip as $segment) {
        if (str_contains($path, DIRECTORY_SEPARATOR . $segment . DIRECTORY_SEPARATOR)) {
            continue 2;
        }
    }

    $filename = $file->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    if (!in_array($extension, $extensions, true) && !in_array($filename, $filenames, true)) {
        continue;
    }

    $content = file_get_contents($path);

    if ($content === false) {
        continue;
    }

    $updated = $content;

    foreach ($replacements as [$from, $to]) {
        $updated = str_replace($from, $to, $updated);
    }

    if ($updated !== $content) {
        file_put_contents($path, $updated);
        $changed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}

sort($changed);

foreach ($changed as $file) {
    echo $file . PHP_EOL;
}

echo 'Total: ' . count($changed) . ' files' . PHP_EOL;
