<?php

$assetsDir = 'C:/Users/ITSmartsourcing/.cursor/projects/c-Users-ITSmartsourcing-Downloads-SSU-Academy/assets';
$destDir = dirname(__DIR__) . '/public/assets/branding';

$matches = glob($assetsDir . '/*SMART_SOURCING_white_font*.png');

if (empty($matches)) {
    fwrite(STDERR, "Logo file not found in {$assetsDir}\n");
    exit(1);
}

$source = $matches[0];
copy($source, $destDir . '/logo-light.png');

echo "Installed logo-light.png from " . basename($source) . "\n";
