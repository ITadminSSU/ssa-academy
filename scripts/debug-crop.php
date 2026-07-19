<?php

declare(strict_types=1);

$files = [
    'IMG_20260718_093014_ROBBYROCKS.jpg' => [850, 1350, 2220, 1650],
    'IMG_20260718_093039_ROBBYROCKS.jpg' => [850, 1100, 2220, 1400],
    'IMG_20260718_093042_ROBBYROCKS.jpg' => [850, 1100, 2220, 1400],
];

$outDir = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\ROBBYROCKS\\_crops';

foreach ($files as $file => [$x1, $y1, $x2, $y2]) {
    $path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\ROBBYROCKS\\' . $file;
    $img = imagecreatefromjpeg($path);
    $crop = imagecrop($img, ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1]);
    imagejpeg($crop, $outDir . '\\check_' . $file, 95);
    imagedestroy($img);
    imagedestroy($crop);
    echo "Saved check crop for {$file}\n";
}
