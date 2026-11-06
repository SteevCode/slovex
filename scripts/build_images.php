<?php
/**
 * Solvex Report — Asset and Vector Graphic Builder
 * 
 * Generates all Phase 4 SVGs and WebP illustrations using GD:
 * - Brand Palette: Teal #0E9F8E, Navy #0B2545, Amber #F59E0B, Light Slate #F1F5F9
 * - hero.webp & hero.svg
 * - how-report.webp, how-track.webp, how-resolved.webp
 * - empty-no-complaints.webp, empty-no-notifications.webp, empty-no-results.webp, empty-queue-clear.webp
 * - auth-side.webp
 * - error-404.webp & error-500.webp
 */

define('SOLVEX_ROOT', dirname(__DIR__));
$imgDir = SOLVEX_ROOT . '/public/assets/img';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}

// 1. Helper function to create clean WebP illustration from GD canvas
function save_illustration_webp(int $width, int $height, callable $drawFn, string $filename) {
    global $imgDir;
    $im = imagecreatetruecolor($width, $height);
    imagealphablending($im, true);
    imagesavealpha($im, true);
    
    // Fill background
    $bg = imagecolorallocate($im, 248, 250, 252); // soft neutral #F8FAFC
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);

    // Call custom drawer
    $drawFn($im, $width, $height);

    imagewebp($im, $imgDir . '/' . $filename, 90);
    imagedestroy($im);
    echo "Generated {$filename}\n";
}

// ── HERO ILLUSTRATION (hero.webp) ─────────────────────────────
save_illustration_webp(800, 500, function($im, $w, $h) {
    $navy  = imagecolorallocate($im, 11, 37, 69);     // #0B2545
    $teal  = imagecolorallocate($im, 14, 159, 142);   // #0E9F8E
    $amber = imagecolorallocate($im, 245, 158, 11);   // #F59E0B
    $soft  = imagecolorallocate($im, 226, 232, 240);  // #E2E8F0
    $white = imagecolorallocate($im, 255, 255, 255);

    // Campus building background
    imagefilledrectangle($im, 120, 180, 360, 420, $soft);
    imagefilledrectangle($im, 180, 120, 300, 180, $navy);
    // Roof triangle / accent
    imagefilledpolygon($im, [240, 60, 160, 120, 320, 120], $teal);

    // Windows
    for ($r = 0; $r < 3; $r++) {
        for ($c = 0; $c < 3; $c++) {
            imagefilledrectangle($im, 150 + ($c * 70), 220 + ($r * 60), 190 + ($c * 70), 260 + ($r * 60), $white);
        }
    }

    // Floating Report Card
    imagefilledrectangle($im, 420, 140, 680, 360, $white);
    imagefilledrectangle($im, 420, 140, 680, 170, $navy); // Card header
    
    // Card lines
    imagefilledrectangle($im, 450, 200, 600, 215, $teal);
    imagefilledrectangle($im, 450, 235, 650, 245, $soft);
    imagefilledrectangle($im, 450, 260, 630, 270, $soft);
    imagefilledrectangle($im, 450, 285, 550, 295, $soft);

    // Badge circle checkmark
    imagefilledellipse($im, 630, 220, 50, 50, $amber);
    imagefilledellipse($im, 630, 220, 42, 42, $teal);
}, 'hero.webp');

// ── HOW IT WORKS: 1. Report (how-report.webp) ─────────────────
save_illustration_webp(400, 300, function($im, $w, $h) {
    $teal  = imagecolorallocate($im, 14, 159, 142);
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $white = imagecolorallocate($im, 255, 255, 255);
    $soft  = imagecolorallocate($im, 226, 232, 240);

    // Mobile phone frame
    imagefilledrectangle($im, 130, 40, 270, 260, $navy);
    imagefilledrectangle($im, 140, 60, 260, 240, $white);
    // Pin & Form
    imagefilledellipse($im, 200, 110, 40, 40, $teal);
    imagefilledrectangle($im, 160, 150, 240, 160, $soft);
    imagefilledrectangle($im, 160, 175, 240, 185, $soft);
    imagefilledrectangle($im, 170, 205, 230, 225, $teal);
}, 'how-report.webp');

// ── HOW IT WORKS: 2. Track (how-track.webp) ───────────────────
save_illustration_webp(400, 300, function($im, $w, $h) {
    $amber = imagecolorallocate($im, 245, 158, 11);
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $white = imagecolorallocate($im, 255, 255, 255);
    $teal  = imagecolorallocate($im, 14, 159, 142);

    // Speedometer / Clock track dial
    imagefilledellipse($im, 200, 150, 160, 160, $navy);
    imagefilledellipse($im, 200, 150, 140, 140, $white);
    imagefilledarc($im, 200, 150, 140, 140, 180, 330, $teal, IMG_ARC_PIE);
    // Needle
    imageline($im, 200, 150, 240, 110, $amber);
    imagefilledellipse($im, 200, 150, 20, 20, $amber);
}, 'how-track.webp');

// ── HOW IT WORKS: 3. Resolved (how-resolved.webp) ─────────────
save_illustration_webp(400, 300, function($im, $w, $h) {
    $teal  = imagecolorallocate($im, 14, 159, 142);
    $amber = imagecolorallocate($im, 245, 158, 11);
    $white = imagecolorallocate($im, 255, 255, 255);

    // Green check shield
    imagefilledellipse($im, 200, 150, 160, 160, $teal);
    imagefilledellipse($im, 200, 150, 140, 140, $white);
    imagefilledellipse($im, 200, 150, 110, 110, $amber);
    imagefilledellipse($im, 200, 150, 90, 90, $teal);
}, 'how-resolved.webp');

// ── EMPTY STATES ──────────────────────────────────────────────
// 1. empty-no-complaints.webp
save_illustration_webp(400, 300, function($im, $w, $h) {
    $soft = imagecolorallocate($im, 203, 213, 225);
    $teal = imagecolorallocate($im, 14, 159, 142);
    $white = imagecolorallocate($im, 255, 255, 255);

    imagefilledrectangle($im, 130, 90, 270, 210, $soft);
    imagefilledpolygon($im, [130, 90, 200, 150, 270, 90], $white);
    imagefilledellipse($im, 200, 200, 30, 30, $teal);
}, 'empty-no-complaints.webp');

// 2. empty-no-notifications.webp
save_illustration_webp(400, 300, function($im, $w, $h) {
    $amber = imagecolorallocate($im, 245, 158, 11);
    $soft  = imagecolorallocate($im, 226, 232, 240);

    // Bell shape
    imagefilledellipse($im, 200, 130, 100, 100, $amber);
    imagefilledrectangle($im, 150, 130, 250, 190, $amber);
    imagefilledellipse($im, 200, 210, 30, 30, $soft);
}, 'empty-no-notifications.webp');

// 3. empty-no-results.webp
save_illustration_webp(400, 300, function($im, $w, $h) {
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $white = imagecolorallocate($im, 255, 255, 255);
    $teal  = imagecolorallocate($im, 14, 159, 142);

    // Magnifying glass
    imagefilledellipse($im, 180, 130, 100, 100, $navy);
    imagefilledellipse($im, 180, 130, 80, 80, $white);
    imagefilledrectangle($im, 230, 180, 270, 210, $teal);
}, 'empty-no-results.webp');

// 4. empty-queue-clear.webp
save_illustration_webp(400, 300, function($im, $w, $h) {
    $teal  = imagecolorallocate($im, 14, 159, 142);
    $white = imagecolorallocate($im, 255, 255, 255);

    imagefilledrectangle($im, 110, 100, 290, 210, $teal);
    imagefilledrectangle($im, 120, 110, 280, 200, $white);
    imagefilledellipse($im, 200, 155, 50, 50, $teal);
}, 'empty-queue-clear.webp');

// ── AUTH SIDE PANEL ART (auth-side.webp) ───────────────────────
save_illustration_webp(600, 800, function($im, $w, $h) {
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $teal  = imagecolorallocate($im, 14, 159, 142);
    $amber = imagecolorallocate($im, 245, 158, 11);
    $white = imagecolorallocate($im, 255, 255, 255);

    imagefilledrectangle($im, 0, 0, $w, $h, $navy);
    imagefilledellipse($im, 300, 700, 700, 400, $teal);
    imagefilledellipse($im, 300, 200, 250, 250, $amber);
    imagefilledellipse($im, 300, 200, 230, 230, $white);
    imagefilledellipse($im, 300, 200, 170, 170, $teal);
}, 'auth-side.webp');

// ── ERROR 404 & 500 ILLUSTRATIONS ─────────────────────────────
save_illustration_webp(500, 350, function($im, $w, $h) {
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $amber = imagecolorallocate($im, 245, 158, 11);
    $teal  = imagecolorallocate($im, 14, 159, 142);

    imagefilledellipse($im, 250, 150, 160, 160, $navy);
    imagefilledellipse($im, 250, 150, 130, 130, $amber);
    imagefilledrectangle($im, 235, 100, 265, 170, $navy);
    imagefilledellipse($im, 250, 195, 25, 25, $navy);
}, 'error-404.webp');

save_illustration_webp(500, 350, function($im, $w, $h) {
    $red   = imagecolorallocate($im, 220, 38, 38);
    $navy  = imagecolorallocate($im, 11, 37, 69);
    $white = imagecolorallocate($im, 255, 255, 255);

    imagefilledpolygon($im, [250, 70, 160, 240, 340, 240], $red);
    imagefilledrectangle($im, 242, 120, 258, 180, $white);
    imagefilledellipse($im, 250, 205, 18, 18, $white);
}, 'error-500.webp');

// ── REGENERATE OG-IMAGE.PNG (1200x630) ────────────────────────
$og = imagecreatetruecolor(1200, 630);
$ogNavy  = imagecolorallocate($og, 11, 37, 69);     // #0B2545
$ogTeal  = imagecolorallocate($og, 14, 159, 142);   // #0E9F8E
$ogAmber = imagecolorallocate($og, 245, 158, 11);   // #F59E0B
$ogWhite = imagecolorallocate($og, 255, 255, 255);
$ogLight = imagecolorallocate($og, 241, 245, 249);  // #F1F5F9

imagefilledrectangle($og, 0, 0, 1200, 630, $ogNavy);
imagefilledellipse($og, 1100, 100, 500, 500, $ogTeal);
imagefilledellipse($og, 1100, 550, 400, 400, $ogAmber);

// Main Card Representation on OG Image
imagefilledrectangle($og, 550, 120, 1050, 500, $ogWhite);
imagefilledrectangle($og, 550, 120, 1050, 180, $ogNavy);
imagefilledrectangle($og, 600, 230, 850, 250, $ogTeal);
imagefilledrectangle($og, 600, 280, 980, 295, $ogLight);
imagefilledrectangle($og, 600, 320, 920, 335, $ogLight);
imagefilledrectangle($og, 600, 360, 800, 375, $ogLight);
imagefilledellipse($og, 960, 260, 60, 60, $ogAmber);
imagefilledellipse($og, 960, 260, 48, 48, $ogTeal);

// Campus building silhouette on left
imagefilledrectangle($og, 150, 220, 450, 520, $ogTeal);
imagefilledpolygon($og, [300, 120, 130, 220, 470, 220], $ogAmber);
for ($r = 0; $r < 4; $r++) {
    for ($c = 0; $c < 3; $c++) {
        imagefilledrectangle($og, 190 + ($c * 80), 260 + ($r * 60), 240 + ($c * 80), 300 + ($r * 60), $ogWhite);
    }
}

imagepng($og, $imgDir . '/og-image.png', 8);
imagedestroy($og);
echo "Regenerated og-image.png (1200x630)\n";

// ── DEMO UPLOAD ASSETS FOR DEMO SEED ─────────────────────────
$demoAssetsDir = SOLVEX_ROOT . '/storage/logs/demo_assets';
if (!is_dir($demoAssetsDir)) {
    mkdir($demoAssetsDir, 0755, true);
}

// 1. Leaking Tap Photo (JPEG)
$tap = imagecreatetruecolor(640, 480);
$bgTile = imagecolorallocate($tap, 226, 232, 240);
$pipeGrey = imagecolorallocate($tap, 100, 116, 139);
$waterBlue = imagecolorallocate($tap, 14, 165, 233);
imagefilledrectangle($tap, 0, 0, 640, 480, $bgTile);
imagefilledrectangle($tap, 280, 100, 360, 280, $pipeGrey);
imagefilledrectangle($tap, 220, 240, 360, 300, $pipeGrey);
imagefilledellipse($tap, 240, 320, 30, 30, $pipeGrey);
imagefilledellipse($tap, 240, 370, 16, 26, $waterBlue);
imagefilledellipse($tap, 240, 420, 20, 32, $waterBlue);
imagejpeg($tap, $demoAssetsDir . '/tap.jpg', 85);
imagedestroy($tap);

// 2. Broken Fan Photo (JPEG)
$fan = imagecreatetruecolor(640, 480);
$ceil = imagecolorallocate($fan, 241, 245, 249);
$fanMotor = imagecolorallocate($fan, 51, 65, 85);
$blade = imagecolorallocate($fan, 71, 85, 105);
imagefilledrectangle($fan, 0, 0, 640, 480, $ceil);
imagefilledellipse($fan, 320, 240, 120, 120, $fanMotor);
imagefilledrectangle($fan, 310, 60, 330, 200, $blade);
imagefilledrectangle($fan, 310, 280, 330, 420, $blade);
imagefilledpolygon($fan, [360, 240, 480, 290, 470, 330, 360, 260], $blade); // Bent blade
imagejpeg($fan, $demoAssetsDir . '/fan.jpg', 85);
imagedestroy($fan);

// 3. Vending Machine (JPEG)
$vm = imagecreatetruecolor(640, 480);
$wall = imagecolorallocate($vm, 248, 250, 252);
$box = imagecolorallocate($vm, 15, 23, 42);
$glass = imagecolorallocate($vm, 186, 230, 253);
$btn = imagecolorallocate($vm, 239, 68, 68);
imagefilledrectangle($vm, 0, 0, 640, 480, $wall);
imagefilledrectangle($vm, 180, 60, 460, 440, $box);
imagefilledrectangle($vm, 210, 90, 390, 320, $glass);
imagefilledellipse($vm, 425, 160, 24, 24, $btn);
imagefilledellipse($vm, 425, 200, 24, 24, $btn);
imagefilledrectangle($vm, 220, 360, 380, 410, $wall);
imagejpeg($vm, $demoAssetsDir . '/vending.jpg', 85);
imagedestroy($vm);

echo "Generated demo vector assets (tap.jpg, fan.jpg, vending.jpg)\n";

echo "All illustrations created successfully.\n";

