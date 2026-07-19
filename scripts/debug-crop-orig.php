<?php

declare(strict_types=1);

$files = ['IMG_20260718_093014.jpg', 'IMG_20260718_093039.jpg', 'IMG_20260718_093042.jpg'];
$outDir = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\ROBBYROCKS\\_crops';
$crops = [
    'IMG_20260718_093014.jpg' => [900, 1050, 2200, 1400],
    'IMG_20260718_093039.jpg' => [900, 1000, 2200, 1350],
    'IMG_20260718_093042.jpg' => [900, 1000, 2200, 1350],
];

foreach ($files as $file) {
    [$x1, $y1, $x2, $y2] = $crops[$file];
    $path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\' . $file;
    $img = imagecreatefromjpeg($path);
    $crop = imagecrop($img, ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1]);
    imagejpeg($crop, $outDir . '\\orig_' . $file, 95);
    imagedestroy($img);
    imagedestroy($crop);
    echo "Saved orig crop for {$file}\n";
}
