<?php

declare(strict_types=1);

require __DIR__ . '/jersey-name-lib.php';

$files = ['IMG_20260718_093014.jpg', 'IMG_20260718_093039.jpg', 'IMG_20260718_093042.jpg'];

foreach ($files as $file) {
    $path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\' . $file;
    $img = imagecreatefromjpeg($path);
    $w = imagesx($img);
    $h = imagesy($img);
    $emblemTop = findEmblemTop($img, $w, $h);
    $region = findNameRegion($img, $w, $h);
    echo "{$file} emblemTop={$emblemTop} (" . round($emblemTop / $h * 100, 1) . "%) region=" . json_encode($region);
    if ($region) {
        echo ' yPct=' . round($region[1] / $h * 100, 1) . '%-' . round($region[3] / $h * 100, 1) . '%';
    }
    echo PHP_EOL;
    imagedestroy($img);
}
