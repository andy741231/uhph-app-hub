<?php

/**
 * Generates the Flipbook favicon from the navbar logo mark.
 *
 * The navbar logo is the Font Awesome 6.5.1 "book-open" glyph (unicode f518)
 * in UH red. This script renders that exact glyph (path data below, from
 * @fortawesome/free-solid-svg-icons 6.5.1) white on the same red rounded
 * square used by the App Hub favicon, so the two sit together consistently
 * in browser tabs.
 *
 * Writes favicon.png (64x64) and favicon.ico (16/32/48/64) into the flipbook
 * root (E:/apps/flipbook), which is the directory IIS serves at /apps/flipbook.
 * Requires the GD extension.
 */

$root = dirname(__DIR__);
$sizes = [16, 32, 48, 64];

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

// Font Awesome 6.5.1 solid "book-open" (viewBox 0 0 576 512).
$iconPath = 'M249.6 471.5c10.8 3.8 22.4-4.1 22.4-15.5V78.6c0-4.2-1.6-8.4-5-11C247.4 52 202.4 32 144 32C93.5 32 46.3 45.3 18.1 56.1C6.8 60.5 0 71.7 0 83.8V454.1c0 11.9 12.8 20.2 24.1 16.5C55.6 460.1 105.5 448 144 448c33.9 0 79 14 105.6 23.5zm76.8 0C353 462 398.1 448 432 448c38.5 0 88.4 12.1 119.9 22.6c11.3 3.8 24.1-4.6 24.1-16.5V83.8c0-12.1-6.8-23.3-18.1-27.6C529.7 45.3 482.5 32 432 32c-58.4 0-103.4 20-123 35.6c-3.3 2.6-5 6.8-5 11V456c0 11.4 11.7 19.3 22.4 15.5z';

/** Tokenize an SVG path `d` string into ['cmd', letter] / ['num', float] tokens. */
function tokenizePath(string $d): array
{
    preg_match_all('/([AaCcHhLlMmQqSsTtVvZz])|(-?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $d, $m, PREG_SET_ORDER);
    $tokens = [];
    foreach ($m as $tok) {
        $tokens[] = $tok[1] !== '' ? ['cmd', $tok[1]] : ['num', (float) $tok[2]];
    }
    return $tokens;
}

/** Recursively flatten a cubic bezier into line segments (ends appended to $out). */
function flattenCubic(array $p0, array $p1, array $p2, array $p3, float $tol, array &$out): void
{
    $dx = $p3[0] - $p0[0];
    $dy = $p3[1] - $p0[1];
    $len = sqrt($dx * $dx + $dy * $dy);
    if ($len < 1e-9) {
        $out[] = $p3;
        return;
    }
    $d1 = abs(($p1[0] - $p3[0]) * $dy - ($p1[1] - $p3[1]) * $dx) / $len;
    $d2 = abs(($p2[0] - $p3[0]) * $dy - ($p2[1] - $p3[1]) * $dx) / $len;
    if ($d1 <= $tol && $d2 <= $tol) {
        $out[] = $p3;
        return;
    }
    // de Casteljau split at t = 0.5
    $p01 = [($p0[0] + $p1[0]) / 2, ($p0[1] + $p1[1]) / 2];
    $p12 = [($p1[0] + $p2[0]) / 2, ($p1[1] + $p2[1]) / 2];
    $p23 = [($p2[0] + $p3[0]) / 2, ($p2[1] + $p3[1]) / 2];
    $p012 = [($p01[0] + $p12[0]) / 2, ($p01[1] + $p12[1]) / 2];
    $p123 = [($p12[0] + $p23[0]) / 2, ($p12[1] + $p23[1]) / 2];
    $p0123 = [($p012[0] + $p123[0]) / 2, ($p012[1] + $p123[1]) / 2];
    flattenCubic($p0, $p01, $p012, $p0123, $tol, $out);
    flattenCubic($p0123, $p123, $p23, $p3, $tol, $out);
}

/**
 * Parse and flatten an SVG path into subpaths of points, transformed by
 * $tx/$ty (scale) and $ox/$oy (offset).
 */
function flattenPath(string $d, float $scale, float $ox, float $oy, float $tol): array
{
    $tokens = tokenizePath($d);
    $argCounts = [
        'M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'Z' => 0,
        'm' => 2, 'l' => 2, 'h' => 1, 'v' => 1, 'c' => 6, 's' => 4, 'q' => 4, 't' => 2, 'z' => 0,
    ];

    $commands = [];
    $i = 0;
    $n = count($tokens);
    while ($i < $n) {
        if ($tokens[$i][0] === 'cmd') {
            $cmd = $tokens[$i][1];
            $i++;
            $need = $argCounts[$cmd];
            $args = [];
            while ($i < $n && $tokens[$i][0] === 'num' && ($need === 0 || count($args) < $need)) {
                $args[] = $tokens[$i][1];
                $i++;
            }
            $commands[] = ['cmd' => $cmd, 'args' => $args];
        } else {
            // Numbers without a command letter: implicit repeat of the previous command.
            $prev = $commands[count($commands) - 1]['cmd'];
            $need = $argCounts[$prev];
            $cmd = $prev;
            if ($cmd === 'M') {
                $cmd = 'L';
            } elseif ($cmd === 'm') {
                $cmd = 'l';
            }
            $args = [];
            while ($i < $n && $tokens[$i][0] === 'num' && count($args) < max(1, $need)) {
                $args[] = $tokens[$i][1];
                $i++;
            }
            $commands[] = ['cmd' => $cmd, 'args' => $args];
        }
    }

    $subpaths = [];
    $current = null;
    $x = 0.0;
    $y = 0.0;
    $startX = 0.0;
    $startY = 0.0;
    $ctrlX = 0.0;
    $ctrlY = 0.0;
    $lastWasCurve = false;

    $push = function () use (&$current, &$subpaths) {
        if ($current !== null && count($current) > 0) {
            $subpaths[] = $current;
        }
        $current = [];
    };

    $lineTo = function (float $nx, float $ny) use (&$current, &$x, &$y, &$ctrlX, &$ctrlY, &$lastWasCurve, $scale, $ox, $oy) {
        $current[] = [$nx * $scale + $ox, $ny * $scale + $oy];
        $x = $nx;
        $y = $ny;
        $ctrlX = $nx;
        $ctrlY = $ny;
        $lastWasCurve = false;
    };

    $curveTo = function (float $c1x, float $c1y, float $c2x, float $c2y, float $ex, float $ey) use (&$current, &$x, &$y, &$ctrlX, &$ctrlY, &$lastWasCurve, $scale, $ox, $oy) {
        $p0 = [$x * $scale + $ox, $y * $scale + $oy];
        $p1 = [$c1x * $scale + $ox, $c1y * $scale + $oy];
        $p2 = [$c2x * $scale + $ox, $c2y * $scale + $oy];
        $p3 = [$ex * $scale + $ox, $ey * $scale + $oy];
        flattenCubic($p0, $p1, $p2, $p3, 0.6, $current);
        $x = $ex;
        $y = $ey;
        $ctrlX = $c2x;
        $ctrlY = $c2y;
        $lastWasCurve = true;
    };

    foreach ($commands as $cmd) {
        $a = $cmd['args'];
        switch ($cmd['cmd']) {
            case 'M':
                $push();
                $current = [];
                $startX = $a[0];
                $startY = $a[1];
                $lineTo($a[0], $a[1]);
                break;
            case 'm':
                $push();
                $current = [];
                $startX = $x + $a[0];
                $startY = $y + $a[1];
                $lineTo($startX, $startY);
                break;
            case 'L':
                $lineTo($a[0], $a[1]);
                break;
            case 'l':
                $lineTo($x + $a[0], $y + $a[1]);
                break;
            case 'H':
                $lineTo($a[0], $y);
                break;
            case 'h':
                $lineTo($x + $a[0], $y);
                break;
            case 'V':
                $lineTo($x, $a[0]);
                break;
            case 'v':
                $lineTo($x, $y + $a[0]);
                break;
            case 'C':
                $curveTo($a[0], $a[1], $a[2], $a[3], $a[4], $a[5]);
                break;
            case 'c':
                $curveTo($x + $a[0], $y + $a[1], $x + $a[2], $y + $a[3], $x + $a[4], $y + $a[5]);
                break;
            case 'S':
                if ($lastWasCurve) {
                    $curveTo(2 * $x - $ctrlX, 2 * $y - $ctrlY, $a[0], $a[1], $a[2], $a[3]);
                } else {
                    $curveTo($x, $y, $a[0], $a[1], $a[2], $a[3]);
                }
                break;
            case 's':
                if ($lastWasCurve) {
                    $curveTo(2 * $x - $ctrlX, 2 * $y - $ctrlY, $x + $a[0], $y + $a[1], $x + $a[2], $y + $a[3]);
                } else {
                    $curveTo($x, $y, $x + $a[0], $y + $a[1], $x + $a[2], $y + $a[3]);
                }
                break;
            case 'Q':
                // Convert quadratic to cubic.
                $curveTo(
                    $x + 2 / 3 * ($a[0] - $x), $y + 2 / 3 * ($a[1] - $y),
                    $a[2] + 2 / 3 * ($a[0] - $a[2]), $a[3] + 2 / 3 * ($a[1] - $a[3]),
                    $a[2], $a[3],
                );
                break;
            case 'q':
                $qx = $x + $a[0];
                $qy = $y + $a[1];
                $ex = $x + $a[2];
                $ey = $y + $a[3];
                $curveTo(
                    $x + 2 / 3 * ($qx - $x), $y + 2 / 3 * ($qy - $y),
                    $ex + 2 / 3 * ($qx - $ex), $ey + 2 / 3 * ($qy - $ey),
                    $ex, $ey,
                );
                break;
            case 'T':
                if ($lastWasCurve) {
                    $qx = 2 * $x - $ctrlX;
                    $qy = 2 * $y - $ctrlY;
                } else {
                    $qx = $x;
                    $qy = $y;
                }
                $ex = $a[0];
                $ey = $a[1];
                $curveTo(
                    $x + 2 / 3 * ($qx - $x), $y + 2 / 3 * ($qy - $y),
                    $ex + 2 / 3 * ($qx - $ex), $ey + 2 / 3 * ($qy - $ey),
                    $ex, $ey,
                );
                break;
            case 't':
                if ($lastWasCurve) {
                    $qx = 2 * $x - $ctrlX;
                    $qy = 2 * $y - $ctrlY;
                } else {
                    $qx = $x;
                    $qy = $y;
                }
                $ex = $x + $a[0];
                $ey = $y + $a[1];
                $curveTo(
                    $x + 2 / 3 * ($qx - $x), $y + 2 / 3 * ($qy - $y),
                    $ex + 2 / 3 * ($qx - $ex), $ey + 2 / 3 * ($qy - $ey),
                    $ex, $ey,
                );
                break;
            case 'Z':
            case 'z':
                $lineTo($startX, $startY);
                $push();
                $lastWasCurve = false;
                break;
        }
    }
    $push();

    return $subpaths;
}

/** Even-odd scanline fill of subpaths with $color (coords already in pixel space). */
function fillSubpaths(GdImage $im, array $subpaths, int $color): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    for ($py = 0; $py < $h; $py++) {
        $testY = $py + 0.5;
        $hits = [];
        foreach ($subpaths as $pts) {
            $cnt = count($pts);
            for ($k = 0; $k < $cnt; $k++) {
                $p1 = $pts[$k];
                $p2 = $pts[($k + 1) % $cnt];
                $y1 = $p1[1];
                $y2 = $p2[1];
                if (($y1 <= $testY && $y2 > $testY) || ($y2 <= $testY && $y1 > $testY)) {
                    $t = ($testY - $y1) / ($y2 - $y1);
                    $hits[] = $p1[0] + $t * ($p2[0] - $p1[0]);
                }
            }
        }
        sort($hits);
        for ($k = 0; $k + 1 < count($hits); $k += 2) {
            $x0 = max(0, (int) ceil($hits[$k]));
            $x1 = min($w - 1, (int) floor($hits[$k + 1]));
            for ($px = $x0; $px <= $x1; $px++) {
                imagesetpixel($im, $px, $py, $color);
            }
        }
    }
}

/** Red rounded square (matching the App Hub favicon) with the white book glyph. */
function drawIcon(int $size, string $iconPath): GdImage
{
    $ss = 4; // supersample factor for anti-aliasing
    $canvasSize = $size * $ss;

    $im = imagecreatetruecolor($canvasSize, $canvasSize);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $canvasSize, $canvasSize, $transparent);

    // Vertical red gradient (Cougar red -> dark red), same as the App Hub mark.
    for ($y = 0; $y < $canvasSize; $y++) {
        $t = $y / max(1, $canvasSize - 1);
        $color = imagecolorallocate(
            $im,
            (int) (209 + (122 - 209) * $t),
            (int) (24 + (12 - 24) * $t),
            (int) (48 + (29 - 48) * $t),
        );
        imagefilledrectangle($im, 0, $y, $canvasSize, $y, $color);
    }

    // Rounded corners: punch out the area outside each corner arc.
    $radius = max(1, (int) round($canvasSize * 0.24));
    $center = $radius - 1;
    for ($y = 0; $y < $radius; $y++) {
        for ($x = 0; $x < $radius; $x++) {
            $dx = $x - $center;
            $dy = $y - $center;
            if (sqrt($dx * $dx + $dy * $dy) > $radius) {
                imagesetpixel($im, $x, $y, $transparent);
                imagesetpixel($im, $canvasSize - 1 - $x, $y, $transparent);
                imagesetpixel($im, $x, $canvasSize - 1 - $y, $transparent);
                imagesetpixel($im, $canvasSize - 1 - $x, $canvasSize - 1 - $y, $transparent);
            }
        }
    }

    // White book glyph, occupying ~78% of the square, centered.
    $scale = ($canvasSize * 0.78) / 576.0; // icon viewBox is 576 x 512
    $ox = ($canvasSize - 576 * $scale) / 2;
    $oy = ($canvasSize - 512 * $scale) / 2;
    $subpaths = flattenPath($iconPath, $scale, $ox, $oy, 0.6);
    $white = imagecolorallocate($im, 255, 255, 255);
    fillSubpaths($im, $subpaths, $white);

    // Downsample for anti-aliasing.
    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagecopyresampled($final, $im, 0, 0, 0, 0, $size, $size, $canvasSize, $canvasSize);
    imagedestroy($im);

    return $final;
}

// PNG at 64x64 for the primary favicon link.
$pngPath = $root.'/favicon.png';
imagepng(drawIcon(64, $iconPath), $pngPath);
echo "Wrote {$pngPath}\n";

// Multi-size ICO (PNG-compressed entries, supported by all modern browsers).
$images = [];
foreach ($sizes as $size) {
    ob_start();
    imagepng(drawIcon($size, $iconPath));
    $images[$size] = ob_get_clean();
}

$ico = pack('vvv', 0, 1, count($images)); // ICONDIR
$offset = 6 + 16 * count($images);
foreach ($sizes as $size) {
    $data = $images[$size];
    $ico .= pack(
        'CCCCvvVV',
        $size === 256 ? 0 : $size,
        $size === 256 ? 0 : $size,
        0,
        0,
        1,
        32,
        strlen($data),
        $offset,
    );
    $offset += strlen($data);
}
foreach ($images as $data) {
    $ico .= $data;
}

$icoPath = $root.'/favicon.ico';
file_put_contents($icoPath, $ico);
echo "Wrote {$icoPath} (".strlen($ico)." bytes)\n";
