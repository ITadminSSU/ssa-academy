<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$source = $argv[1] ?? 'C:/Users/ITSmartsourcing/Downloads/Final logo SSA.png';

if (!is_file($source)) {
    fwrite(STDERR, "Source logo not found: {$source}\n");
    fwrite(STDERR, "Usage: php scripts/install-brand-logos.php [path-to-logo.png]\n");
    exit(1);
}

$brandingDir = $projectRoot . '/public/assets/branding';

$wordmarkTargets = [
    'ssa-academy-logo.png',
    'logo-light.png',
    'logo-dark.png',
    'logo-icon.png',
];

foreach ($wordmarkTargets as $filename) {
    $destination = $brandingDir . '/' . $filename;
    if (!copy($source, $destination)) {
        fwrite(STDERR, "Failed to copy logo to {$filename}\n");
        exit(1);
    }

    echo "Installed {$filename}\n";
}

passthru('php ' . escapeshellarg($projectRoot . '/scripts/generate-favicons.php') . ' ' . escapeshellarg($source), $exitCode);

exit($exitCode);
