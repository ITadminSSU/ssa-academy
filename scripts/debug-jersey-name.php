<?php
$files = ['IMG_20260718_093229.jpg','IMG_20260718_093251.jpg','IMG_20260718_093306.jpg'];
foreach ($files as $f) {
    $p = 'C:\\Users\\ITSmartsourcing\\Pictures\\Photoshoot\\Jam Distor\\' . $f;
    $i = imagecreatefromjpeg($p);
    $w = imagesx($i); $h = imagesy($i);
    echo "$f {$w}x$h\n";
    $bestY = 0; $best = 0;
    for ($y = (int) ($h * 0.26); $y < (int) ($h * 0.44); $y++) {
        $s = 0;
        for ($x = (int) ($w * 0.32); $x < (int) ($w * 0.68); $x++) {
            $rgb = imagecolorat($i, $x, $y);
            $r = ($rgb >> 16) & 255; $g = ($rgb >> 8) & 255; $b = $rgb & 255;
            $max = max($r, $g, $b); $min = min($r, $g, $b);
            if ($max > 90 && ($max - $min) > 55 && (0.299 * $r + 0.587 * $g + 0.114 * $b) < 235) {
                $s++;
            }
        }
        if ($s > $best) { $best = $s; $bestY = $y; }
    }
    echo '  emblemTop=' . $bestY . ' (' . round($bestY / $h, 3) . ") best=$best\n";
    $st = max(0, $bestY - 90);
    $sb = max($st + 8, $bestY - 8);
    $hits = 0; $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
    for ($y = $st; $y < $sb; $y++) {
        for ($x = (int) ($w * 0.38); $x < (int) ($w * 0.62); $x++) {
            $rgb = imagecolorat($i, $x, $y);
            $r = ($rgb >> 16) & 255; $g = ($rgb >> 8) & 255; $b = $rgb & 255;
            $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            if ($l >= 200 && $r >= 175 && $g >= 175 && $b >= 175) {
                $hits++;
                $minX = min($minX, $x); $maxX = max($maxX, $x);
                $minY = min($minY, $y); $maxY = max($maxY, $y);
            }
        }
    }
    echo "  search $st-$sb hits=$hits box=[$minX,$minY,$maxX,$maxY] w=" . ($maxX - $minX + 1) . ' h=' . ($maxY - $minY + 1) . "\n\n";
}
