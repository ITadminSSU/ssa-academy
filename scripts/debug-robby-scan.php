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

function findEmblemBounds(GdImage $img, int $width, int $height): array
{
    $xStart = (int) round($width * 0.30);
    $xEnd = (int) round($width * 0.70);
    $yStart = (int) round($height * 0.30);
    $yEnd = (int) round($height * 0.55);

    $minX = $width;
    $minY = $height;
    $maxX = 0;
    $maxY = 0;
    $hits = 0;

    for ($y = $yStart; $y < $yEnd; $y++) {
        for ($x = $xStart; $x < $xEnd; $x++) {
            if (!isSaturatedPixel(imagecolorat($img, $x, $y))) {
                continue;
            }
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
            $hits++;
        }
    }

    return compact('minX', 'minY', 'maxX', 'maxY', 'hits');
}

$files = ['IMG_20260718_093014.jpg', 'IMG_20260718_093039.jpg', 'IMG_20260718_093042.jpg'];

foreach ($files as $file) {
    $path = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Robby Glindro\\' . $file;
    $img = imagecreatefromjpeg($path);
    $w = imagesx($img);
    $h = imagesy($img);
    $emblem = findEmblemBounds($img, $w, $h);

    echo "\n{$file} ({$w}x{$h}) emblem bounds: top={$emblem['minY']} bottom={$emblem['maxY']} hits={$emblem['hits']}\n";

    $xStart = (int) round($w * 0.32);
    $xEnd = (int) round($w * 0.68);
    $scanTop = max(0, $emblem['minY'] - 200);
    $scanBottom = $emblem['minY'] - 5;

    $bands = [];
    $inBand = false;
    $bandStart = 0;
    $bandRows = [];

    for ($y = $scanTop; $y <= $scanBottom; $y++) {
        $count = 0;
        $rowMinX = $w;
        $rowMaxX = 0;
        for ($x = $xStart; $x < $xEnd; $x++) {
            if (isBrightTextPixel(imagecolorat($img, $x, $y))) {
                $count++;
                $rowMinX = min($rowMinX, $x);
                $rowMaxX = max($rowMaxX, $x);
            }
        }

        $active = $count >= 8;
        if ($active) {
            $bandRows[$y] = ['count' => $count, 'width' => $rowMaxX - $rowMinX + 1, 'minX' => $rowMinX, 'maxX' => $rowMaxX];
        }

        if ($active && !$inBand) {
            $inBand = true;
            $bandStart = $y;
        } elseif (!$active && $inBand) {
            $bands[] = ['start' => $bandStart, 'end' => $y - 1];
            $inBand = false;
        }
    }

    if ($inBand) {
        $bands[] = ['start' => $bandStart, 'end' => $scanBottom];
    }

    foreach ($bands as $i => $band) {
        $rows = array_filter($bandRows, static fn ($_, $y) => $y >= $band['start'] && $y <= $band['end'], ARRAY_FILTER_USE_BOTH);
        $avgWidth = (int) round(array_sum(array_column($rows, 'width')) / max(1, count($rows)));
        $maxCount = max(array_column($rows, 'count'));
        $heightBand = $band['end'] - $band['start'] + 1;
        echo "  band {$i}: y={$band['start']}-{$band['end']} h={$heightBand} avgWidth={$avgWidth} maxCount={$maxCount}\n";
    }

    imagedestroy($img);
}
