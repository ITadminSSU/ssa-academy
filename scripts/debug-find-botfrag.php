<?php

declare(strict_types=1);

function pixelLuma(int $rgb): float
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    return ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
}

function isBrightTextPixel(int $rgb): bool
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $luma = pixelLuma($rgb);

    return $luma >= 198 && $r >= 170 && $g >= 170 && $b >= 170 && abs($r - $g) < 45 && abs($g - $b) < 45;
}

$file = $argv[1] ?? 'IMG_20260718_093014.jpg';
$path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\' . $file;
$img = imagecreatefromjpeg($path);
$w = imagesx($img);
$h = imagesy($img);
$xStart = (int) round($w * 0.32);
$xEnd = (int) round($w * 0.68);

$bestY = 0;
$bestWidth = 0;

for ($y = (int) round($h * 0.20); $y < (int) round($h * 0.55); $y++) {
    $count = 0;
    $minX = $xEnd;
    $maxX = $xStart;
    for ($x = $xStart; $x < $xEnd; $x++) {
        if (isBrightTextPixel(imagecolorat($img, $x, $y))) {
            $count++;
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
        }
    }
    $width = $count > 0 ? $maxX - $minX + 1 : 0;
    if ($count >= 300 && $width > $bestWidth) {
        $bestWidth = $width;
        $bestY = $y;
    }
}

echo "{$file}: peak wide text row y={$bestY} (" . round($bestY / $h * 100, 1) . "%) width={$bestWidth}\n";

// find band around peak
$bandTop = $bestY;
$bandBottom = $bestY;
for ($y = $bestY; $y > (int) round($h * 0.20); $y--) {
    $count = 0;
    for ($x = $xStart; $x < $xEnd; $x++) {
        if (isBrightTextPixel(imagecolorat($img, $x, $y))) {
            $count++;
        }
    }
    if ($count < 120) {
        break;
    }
    $bandTop = $y;
}
for ($y = $bestY; $y < (int) round($h * 0.55); $y++) {
    $count = 0;
    for ($x = $xStart; $x < $xEnd; $x++) {
        if (isBrightTextPixel(imagecolorat($img, $x, $y))) {
            $count++;
        }
    }
    if ($count < 120) {
        break;
    }
    $bandBottom = $y;
}

echo "  full bright band: y={$bandTop}-{$bandBottom} (" . round($bandTop / $h * 100, 1) . "%-" . round($bandBottom / $h * 100, 1) . "%)\n";
