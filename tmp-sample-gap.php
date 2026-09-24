<?php

$p = 'C:\\Users\\ITSmartsourcing\\.cursor\\projects\\c-Users-ITSmartsourcing-Downloads-SSU-Academy\\assets\\c__Users_ITSmartsourcing_AppData_Roaming_Cursor_User_workspaceStorage_a48867a02a6748d2b4289ed6946a4074_images_image-bff5c8d5-7a0c-4603-b952-eedc2f96eab1.png';
$im = imagecreatefrompng($p);
$w = imagesx($im);
$h = imagesy($im);
echo "email size {$w} x {$h}\n";

function rgb($im, $x, $y)
{
    $c = imagecolorat($im, $x, $y);

    return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
}

function isNavy($r)
{
    return $r[2] > $r[0] + 20 && $r[2] > 70 && $r[0] < 90 && $r[1] < 90;
}

function isRed($r)
{
    return $r[0] > 90 && $r[0] > $r[1] * 1.4 && $r[0] > $r[2] * 1.3;
}

$bg = rgb($im, 10, 10);
echo 'bg 10,10: '.implode(',', $bg)."\n";

for ($y = 0; $y < $h; $y += 3) {
    $navy = [];
    $red = [];
    for ($x = 0; $x < $w; $x += 2) {
        $px = rgb($im, $x, $y);
        if (isNavy($px)) {
            $navy[] = $x;
        }
        if (isRed($px)) {
            $red[] = $x;
        }
    }
    if (count($navy) > 20) {
        $mid = $navy[(int) (count($navy) / 2)];
        $col = rgb($im, $mid, $y);
        $span = $navy[count($navy) - 1] - $navy[0];
        echo "y={$y} NAVY x={$navy[0]}-{$navy[count($navy) - 1]} span={$span} color=".implode(',', $col).' n='.count($navy)."\n";
    }
    if (count($red) > 20) {
        $mid = $red[(int) (count($red) / 2)];
        $col = rgb($im, $mid, $y);
        $span = $red[count($red) - 1] - $red[0];
        echo "y={$y} RED  x={$red[0]}-{$red[count($red) - 1]} span={$span} color=".implode(',', $col).' n='.count($red)."\n";
    }
}
