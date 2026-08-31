<?php

/**
 * Generates the UHPH App Hub default favicon (red rounded square with "AH").
 *
 * Writes public/favicon.png (64x64) and public/favicon.ico (multi-size) in the
 * Laravel public dir, then copies them to the IIS served root (E:/apps) because
 * the hub is served from the parent application and app-hub/public is blocked.
 * Requires the GD extension.
 */
$publicDir = dirname(__DIR__).'/public';
$servedRoot = dirname(__DIR__, 2); // E:/apps
$font = 'C:/Windows/Fonts/arialbd.ttf';
$sizes = [16, 32, 48, 64];

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}
if (! is_file($font)) {
    fwrite(STDERR, "Bold font not found at {$font}.\n");
    exit(1);
}

function drawIcon(int $size, string $font): GdImage
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $size, $size, $transparent);

    // Vertical red gradient (Cougar red -> dark red).
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        $color = imagecolorallocate(
            $im,
            (int) (209 + (122 - 209) * $t),
            (int) (24 + (12 - 24) * $t),
            (int) (48 + (29 - 48) * $t),
        );
        imagefilledrectangle($im, 0, $y, $size, $y, $color);
    }

    // Rounded corners: punch out the area outside the corner arc.
    // Each corner arc is centered at (radius-1, radius-1) from its corner.
    $radius = max(1, (int) round($size * 0.24));
    $center = $radius - 1;
    for ($y = 0; $y < $radius; $y++) {
        for ($x = 0; $x < $radius; $x++) {
            $dx = $x - $center;
            $dy = $y - $center;
            if (sqrt($dx * $dx + $dy * $dy) > $radius) {
                imagesetpixel($im, $x, $y, $transparent);
                imagesetpixel($im, $size - 1 - $x, $y, $transparent);
                imagesetpixel($im, $x, $size - 1 - $y, $transparent);
                imagesetpixel($im, $size - 1 - $x, $size - 1 - $y, $transparent);
            }
        }
    }

    // Centered "AH" monogram (anti-aliased text needs alpha blending enabled).
    imagealphablending($im, true);
    $text = 'AH';
    $fontSize = $size * 0.44;
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = $box[2] - $box[0];
    $textHeight = $box[1] - $box[7];
    $x = (int) round(($size - $textWidth) / 2 - $box[0]);
    $y = (int) round(($size - $textHeight) / 2 - $box[7]);
    imagettftext($im, $fontSize, 0, $x, $y, imagecolorallocate($im, 255, 255, 255), $font, $text);

    return $im;
}

// PNG at 64x64 for the primary favicon link.
$pngPath = $publicDir.'/favicon.png';
imagepng(drawIcon(64, $font), $pngPath);
echo "Wrote {$pngPath}\n";

// Multi-size ICO (PNG-compressed entries, supported by all modern browsers).
$images = [];
foreach ($sizes as $size) {
    ob_start();
    imagepng(drawIcon($size, $font));
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

foreach (['favicon.png', 'favicon.ico'] as $file) {
    copy($publicDir.'/'.$file, $servedRoot.'/'.$file);
    echo "Copied {$file} to {$servedRoot}\n";
}
