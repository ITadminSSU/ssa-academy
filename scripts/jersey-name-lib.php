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

function isSaturatedPixel(int $rgb): bool
{
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);

    return $max > 90 && ($max - $min) > 55 && pixelLuma($rgb) < 235;
}

function findEmblemTop(GdImage $img, int $width, int $height): int
{
    $xStart = (int) round($width * 0.30);
    $xEnd = (int) round($width * 0.70);
    $yStart = (int) round($height * 0.30);
    $yEnd = (int) round($height * 0.52);

    $peakY = $yStart;
    $peakScore = 0;
    $rowScores = [];

    for ($y = $yStart; $y < $yEnd; $y++) {
        $score = 0;
        for ($x = $xStart; $x < $xEnd; $x++) {
            if (isSaturatedPixel(imagecolorat($img, $x, $y))) {
                $score++;
            }
        }

        $rowScores[$y] = $score;
        if ($score > $peakScore) {
            $peakScore = $score;
            $peakY = $y;
        }
    }

    if ($peakScore < 80) {
        return (int) round($height * 0.40);
    }

    $threshold = (int) max(35, round($peakScore * 0.12));
    $emblemTop = $peakY;

    for ($y = $peakY; $y >= $yStart; $y--) {
        if (($rowScores[$y] ?? 0) >= $threshold) {
            $emblemTop = $y;
            continue;
        }

        break;
    }

    return $emblemTop;
}

function measureBrightRow(GdImage $img, int $y, int $xStart, int $xEnd): array
{
    $count = 0;
    $minX = $xEnd;
    $maxX = $xStart;

    for ($x = $xStart; $x < $xEnd; $x++) {
        if (!isBrightTextPixel(imagecolorat($img, $x, $y))) {
            continue;
        }

        $count++;
        $minX = min($minX, $x);
        $maxX = max($maxX, $x);
    }

    return [
        'count' => $count,
        'width' => $count > 0 ? $maxX - $minX + 1 : 0,
        'minX' => $count > 0 ? $minX : $xStart,
        'maxX' => $count > 0 ? $maxX : $xEnd,
    ];
}

function findNameRegion(GdImage $img, int $width, int $height): ?array
{
    $emblemTop = findEmblemTop($img, $width, $height);
    $xStart = (int) round($width * 0.32);
    $xEnd = (int) round($width * 0.68);
    $columnWidth = $xEnd - $xStart;
    $scanBottom = $emblemTop - 4;
    $scanTop = max(0, $emblemTop - 110);
    $wideThreshold = (int) round($columnWidth * 0.55);
    $minCount = 140;

    $bandEnd = null;
    $bandStart = null;
    $minX = $width;
    $maxX = 0;

    for ($y = $scanBottom; $y >= $scanTop; $y--) {
        $row = measureBrightRow($img, $y, $xStart, $xEnd);
        $isText = $row['count'] >= $minCount && $row['width'] >= $wideThreshold;

        if (!$isText) {
            if ($bandEnd !== null) {
                break;
            }

            continue;
        }

        if ($bandEnd === null) {
            $bandEnd = $y;
        }

        $bandStart = $y;
        $minX = min($minX, $row['minX']);
        $maxX = max($maxX, $row['maxX']);
    }

    if ($bandEnd === null || $bandStart === null || $maxX <= $minX) {
        return null;
    }

    $padX = 14;
    $padY = 8;

    return [
        max(0, $minX - $padX),
        max(0, $bandStart - $padY),
        min($width - 1, $maxX + $padX),
        min($height - 1, $bandEnd + $padY),
    ];
}

function sampleDarkFillColor(GdImage $img, int $x1, int $y1, int $x2, int $y2): int
{
    $samples = [];

    for ($y = max(0, $y1 - 20); $y <= min(imagesy($img) - 1, $y2 + 20); $y++) {
        for ($x = max(0, $x1 - 20); $x <= min(imagesx($img) - 1, $x2 + 20); $x++) {
            $inside = $x >= $x1 && $x <= $x2 && $y >= $y1 && $y <= $y2;
            if ($inside) {
                continue;
            }

            $rgb = imagecolorat($img, $x, $y);
            $luma = pixelLuma($rgb);
            if ($luma < 140 && !isBrightTextPixel($rgb)) {
                $samples[] = $rgb;
            }
        }
    }

    if ($samples === []) {
        return imagecolorallocate($img, 28, 30, 34);
    }

    usort($samples, static fn (int $a, int $b): int => pixelLuma($a) <=> pixelLuma($b));
    $rgb = $samples[(int) floor(count($samples) / 2)];
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    return imagecolorallocate($img, $r, $g, $b);
}

function patchBackground(GdImage $img, int $x1, int $y1, int $x2, int $y2): void
{
    $fill = sampleDarkFillColor($img, $x1, $y1, $x2, $y2);
    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $fill);
}

function drawOutlinedText(GdImage $img, string $fontBold, string $text, int $fontSize, int $textX, int $textY): void
{
    $white = imagecolorallocate($img, 255, 255, 255);
    $outline = imagecolorallocate($img, 20, 20, 20);

    foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$ox, $oy]) {
        imagettftext($img, $fontSize, 0, $textX + $ox, $textY + $oy, $outline, $fontBold, $text);
    }

    imagettftext($img, $fontSize, 0, $textX, $textY, $white, $fontBold, $text);
}

function replaceName(GdImage $img, string $fontBold, string $newName, ?array $manualRegion = null): bool
{
    $width = imagesx($img);
    $height = imagesy($img);
    $region = $manualRegion ?? findNameRegion($img, $width, $height);

    if ($region === null) {
        return false;
    }

    [$x1, $y1, $x2, $y2] = $region;
    patchBackground($img, $x1, $y1, $x2, $y2);

    $boxWidth = $x2 - $x1 + 1;
    $boxHeight = $y2 - $y1 + 1;
    $fontSize = max(14, min(64, (int) round($boxHeight * 0.82)));

    do {
        $bbox = imagettfbbox($fontSize, 0, $fontBold, $newName);
        if ($bbox === false) {
            return false;
        }
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);
        if ($textWidth <= $boxWidth * 0.98) {
            break;
        }
        $fontSize--;
    } while ($fontSize > 10);

    $textX = (int) round($x1 + (($boxWidth - $textWidth) / 2));
    $textY = (int) round($y1 + (($boxHeight + $textHeight) / 2) - 2);
    drawOutlinedText($img, $fontBold, $newName, $fontSize, $textX, $textY);

    return true;
}

/**
 * @return array{0:float,1:float,2:float,3:float}|null
 */
function manualNameRegionForFile(string $file): ?array
{
    return match ($file) {
        'IMG_20260718_093014.jpg' => [0.31, 0.352, 0.69, 0.388],
        'IMG_20260718_093039.jpg' => [0.31, 0.292, 0.69, 0.328],
        'IMG_20260718_093042.jpg' => [0.31, 0.292, 0.69, 0.328],
        default => null,
    };
}

function resolveNameRegion(GdImage $img, int $width, int $height, string $file): ?array
{
    $manual = manualNameRegionForFile($file);
    if ($manual !== null) {
        return [
            (int) round($manual[0] * $width),
            (int) round($manual[1] * $height),
            (int) round($manual[2] * $width),
            (int) round($manual[3] * $height),
        ];
    }

    return findNameRegion($img, $width, $height);
}
