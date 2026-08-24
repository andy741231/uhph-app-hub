<?php

/**
 * Generates the Grant Review favicon from the site logo.
 *
 * Every Grant Review page header (guest, admin, reviewer, submitter) presents
 * the logo as a white heroicons v2 "trophy" icon (24x24 outline, stroke 1.5)
 * on a red rounded square. This script rasterizes that exact outline icon
 * white on the same red rounded square used by the App Hub and Flipbook
 * favicons, so the whole /apps suite sits together consistently in tabs.
 *
 * The trophy path data below is the heroicons v2 outline "trophy" from
 * vendor/blade-ui-kit/blade-heroicons/resources/svg/o-trophy.svg. Because it
 * is a stroke-based icon, the rasterizer expands each flattened path segment
 * into a thick quad plus round caps/joins and union-fills everything (4x
 * supersampling for anti-aliasing).
 *
 * Writes favicon.png (64x64) and favicon.ico (16/32/48/64) into public/, which
 * is the IIS document root for /apps/grant-review. Requires the GD extension.
 */
$publicDir = dirname(__DIR__).'/public';
$sizes = [16, 32, 48, 64];

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

// Heroicons v2 outline "trophy" (viewBox 0 0 24 24, stroke-width 1.5).
$iconPath = 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0';

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

/** SVG elliptical arc -> polyline points (endpoint parameterization). */
function arcToPoints(float $x1, float $y1, float $rx, float $ry, float $phi, bool $largeArc, bool $sweep, float $x2, float $y2): array
{
    $rx = abs($rx);
    $ry = abs($ry);
    if ($rx < 1e-9 || $ry < 1e-9 || (abs($x1 - $x2) < 1e-9 && abs($y1 - $y2) < 1e-9)) {
        return [[$x2, $y2]];
    }
    $cosP = cos($phi);
    $sinP = sin($phi);
    $dx2 = ($x1 - $x2) / 2;
    $dy2 = ($y1 - $y2) / 2;
    $x1p = $cosP * $dx2 + $sinP * $dy2;
    $y1p = -$sinP * $dx2 + $cosP * $dy2;
    $lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
    if ($lambda > 1) {
        $rx *= sqrt($lambda);
        $ry *= sqrt($lambda);
    }
    $rx2 = $rx * $rx;
    $ry2 = $ry * $ry;
    $num = $rx2 * $ry2 - $rx2 * $y1p * $y1p - $ry2 * $x1p * $x1p;
    $den = $rx2 * $y1p * $y1p + $ry2 * $x1p * $x1p;
    $coef = ($largeArc === $sweep ? -1 : 1) * sqrt(max(0.0, $num / max($den, 1e-9)));
    $cxp = $coef * ($rx * $y1p / $ry);
    $cyp = $coef * (-$ry * $x1p / $rx);
    $cx = $cosP * $cxp - $sinP * $cyp + ($x1 + $x2) / 2;
    $cy = $sinP * $cxp + $cosP * $cyp + ($y1 + $y2) / 2;
    $ux = ($x1p - $cxp) / $rx;
    $uy = ($y1p - $cyp) / $ry;
    $vx = (-$x1p - $cxp) / $rx;
    $vy = (-$y1p - $cyp) / $ry;
    $theta1 = atan2($uy, $ux);
    $dot = $ux * $vx + $uy * $vy;
    $cross = $ux * $vy - $uy * $vx;
    $len = sqrt(($ux * $ux + $uy * $uy) * ($vx * $vx + $vy * $vy));
    $delta = acos(max(-1.0, min(1.0, $dot / max($len, 1e-9))));
    // acos loses direction; recover the signed angle from the cross product.
    if ($cross < 0) {
        $delta = -$delta;
    }
    // SVG sweep=1 is clockwise (positive in y-down coords); sweep=0 is counter-clockwise.
    if (! $sweep && $delta > 0) {
        $delta -= 2 * M_PI;
    } elseif ($sweep && $delta < 0) {
        $delta += 2 * M_PI;
    }
    $steps = max(2, (int) ceil(abs($delta) / (M_PI / 12)));
    $pts = [];
    for ($i = 1; $i <= $steps; $i++) {
        $t = $theta1 + $delta * $i / $steps;
        $pts[] = [
            $cx + $rx * cos($t) * $cosP - $ry * sin($t) * $sinP,
            $cy + $rx * cos($t) * $sinP + $ry * sin($t) * $cosP,
        ];
    }
    $pts[count($pts) - 1] = [$x2, $y2];

    return $pts;
}

/**
 * Parse and flatten an SVG path into subpaths of points, transformed by
 * $tx/$ty (scale) and $ox/$oy (offset).
 */
function flattenPath(string $d, float $scale, float $ox, float $oy, float $tol): array
{
    $tokens = tokenizePath($d);
    $argCounts = [
        'M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'A' => 7, 'Z' => 0,
        'm' => 2, 'l' => 2, 'h' => 1, 'v' => 1, 'c' => 6, 's' => 4, 'q' => 4, 't' => 2, 'a' => 7, 'z' => 0,
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
            case 'A':
                foreach (arcToPoints($x, $y, $a[0], $a[1], deg2rad($a[2]), (bool) $a[3], (bool) $a[4], $a[5], $a[6]) as $p) {
                    $lineTo($p[0], $p[1]);
                }
                break;
            case 'a':
                foreach (arcToPoints($x, $y, $a[0], $a[1], deg2rad($a[2]), (bool) $a[3], (bool) $a[4], $x + $a[5], $y + $a[6]) as $p) {
                    $lineTo($p[0], $p[1]);
                }
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

/** Regular n-gon approximating a circle centered at ($cx, $cy). */
function circlePoly(float $cx, float $cy, float $r, int $points): array
{
    $poly = [];
    for ($k = 0; $k < $points; $k++) {
        $t = 2 * M_PI * $k / $points;
        $poly[] = [$cx + $r * cos($t), $cy + $r * sin($t)];
    }

    return $poly;
}

/**
 * Expand flattened path polylines into filled shapes: a thick quad per
 * segment plus round caps at ends and round joins at vertices. Returns a
 * list of polygon subpaths to union-fill.
 */
function strokeSubpaths(array $subpaths, float $strokeWidth): array
{
    $out = [];
    $r = $strokeWidth / 2;
    foreach ($subpaths as $pts) {
        $cnt = count($pts);
        if ($cnt < 2) {
            continue;
        }
        for ($k = 0; $k < $cnt - 1; $k++) {
            $x1 = $pts[$k][0];
            $y1 = $pts[$k][1];
            $x2 = $pts[$k + 1][0];
            $y2 = $pts[$k + 1][1];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len = sqrt($dx * $dx + $dy * $dy);
            if ($len < 1e-9) {
                continue;
            }
            $nx = -$dy / $len * $r;
            $ny = $dx / $len * $r;
            $out[] = [[$x1 + $nx, $y1 + $ny], [$x1 - $nx, $y1 - $ny], [$x2 - $nx, $y2 - $ny], [$x2 + $nx, $y2 + $ny]];
        }
        $out[] = circlePoly($pts[0][0], $pts[0][1], $r, 24);
        $out[] = circlePoly($pts[$cnt - 1][0], $pts[$cnt - 1][1], $r, 24);
        for ($k = 1; $k < $cnt - 1; $k++) {
            $out[] = circlePoly($pts[$k][0], $pts[$k][1], $r, 24);
        }
    }

    return $out;
}

/** Scanline fill of each polygon independently (union, for opaque shapes). */
function fillSubpaths(GdImage $im, array $subpaths, int $color): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    foreach ($subpaths as $pts) {
        $cnt = count($pts);
        if ($cnt < 3) {
            continue;
        }
        $minY = PHP_INT_MAX;
        $maxY = -PHP_INT_MAX;
        foreach ($pts as $p) {
            $minY = min($minY, (int) floor($p[1]));
            $maxY = max($maxY, (int) ceil($p[1]));
        }
        $minY = max(0, $minY);
        $maxY = min($h - 1, $maxY);
        for ($py = $minY; $py <= $maxY; $py++) {
            $testY = $py + 0.5;
            $hits = [];
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
}

/** Red rounded square (matching the App Hub / Flipbook favicons) with the white trophy. */
function drawIcon(int $size, string $iconPath): GdImage
{
    $ss = 4; // supersample factor for anti-aliasing
    $canvasSize = $size * $ss;

    $im = imagecreatetruecolor($canvasSize, $canvasSize);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $canvasSize, $canvasSize, $transparent);

    // Vertical red gradient (Cougar red -> dark red), same as the family marks.
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

    // White trophy occupying ~68% of the square (as in the app's own headers),
    // with a small stroke floor so the 16px entry stays legible.
    $scale = ($canvasSize * 0.68) / 24.0; // trophy viewBox is 24 x 24
    $ox = ($canvasSize - 24 * $scale) / 2;
    $oy = ($canvasSize - 24 * $scale) / 2;
    $strokeFinal = max(1.5 * $size * 0.68 / 24.0, 1.2); // minimum 1.2px at small sizes
    $subpaths = flattenPath($iconPath, $scale, $ox, $oy, 0.6);
    $shapes = strokeSubpaths($subpaths, $strokeFinal * $ss);
    $white = imagecolorallocate($im, 255, 255, 255);
    fillSubpaths($im, $shapes, $white);

    // Downsample for anti-aliasing.
    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagecopyresampled($final, $im, 0, 0, 0, 0, $size, $size, $canvasSize, $canvasSize);
    imagedestroy($im);

    return $final;
}

// PNG at 64x64 for the primary favicon link (replaces nothing; new file).
$pngPath = $publicDir.'/favicon.png';
imagepng(drawIcon(64, $iconPath), $pngPath);
echo "Wrote {$pngPath}\n";

// Multi-size ICO (PNG-compressed entries, supported by all modern browsers).
// Replaces the empty 0-byte scaffold favicon.ico.
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

$icoPath = $publicDir.'/favicon.ico';
file_put_contents($icoPath, $ico);
echo "Wrote {$icoPath} (".strlen($ico)." bytes)\n";
