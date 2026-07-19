<?php

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

function isSaturatedPixel(int $rgb): bool
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);

    return $max > 90 && ($max - $min) > 55 && pixelLuma($rgb) < 235;
}

$file = $argv[1] ?? 'IMG_20260718_093014.jpg';
$path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\' . $file;
$img = imagecreatefromjpeg($path);
$w = imagesx($img);
$h = imagesy($img);
$xStart = (int) round($w * 0.32);
$xEnd = (int) round($w * 0.68);

$emblemTop = 1302; // fixed for 093014 test

echo "emblemTop=$emblemTop\n";
for ($y = $emblemTop - 120; $y < $emblemTop; $y += 5) {
    $count = 0;
    $minX = $w;
    $maxX = 0;
    for ($x = $xStart; $x < $xEnd; $x++) {
        if (isBrightTextPixel(imagecolorat($img, $x, $y))) {
            $count++;
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
        }
    }
    $width = $count > 0 ? $maxX - $minX + 1 : 0;
    echo sprintf("y=%4d count=%4d width=%4d\n", $y, $count, $width);
}
