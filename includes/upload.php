<?php
/**
 * Solvex Report — Upload Pipeline
 * 
 * Secure photo upload handling:
 * - Real MIME inspection via finfo (never trust extension)
 * - File size enforcement (max 5 MB)
 * - Complete EXIF & GPS sanitization via GD re-encoding
 * - Cryptographically random file naming outside public/
 */

if (!defined('SOLVEX_ROOT')) {
    define('SOLVEX_ROOT', dirname(__DIR__));
}

require_once SOLVEX_ROOT . '/includes/config.php';

const UPLOAD_MAX_FILES = 3;
const UPLOAD_MAX_BYTES = 5242880; // 5 MB (5 * 1024 * 1024)
const UPLOAD_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Collect photo uploads from a multi-file $_FILES['photos'] field.
 *
 * @return array{ok: bool, files: array<int, array>, message: string}
 */
function collect_photo_uploads(array $photosField): array
{
    if (empty($photosField['name']) || !is_array($photosField['name'])) {
        return ['ok' => true, 'files' => [], 'message' => ''];
    }

    $files = [];
    $count = count($photosField['name']);
    for ($i = 0; $i < $count; $i++) {
        if (empty($photosField['name'][$i])) {
            continue;
        }
        $files[] = [
            'name'     => $photosField['name'][$i],
            'type'     => $photosField['type'][$i] ?? '',
            'tmp_name' => $photosField['tmp_name'][$i] ?? '',
            'error'    => $photosField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $photosField['size'][$i] ?? 0,
        ];
    }

    if (count($files) > UPLOAD_MAX_FILES) {
        return [
            'ok'      => false,
            'files'   => [],
            'message' => 'A maximum of 3 photos can be uploaded per complaint.',
        ];
    }

    return ['ok' => true, 'files' => $files, 'message' => ''];
}

/**
 * Process a single uploaded image file:
 * validates MIME via finfo, size, re-encodes via GD to strip all EXIF/GPS,
 * and saves to UPLOAD_PATH.
 * 
 * @param array $file $_FILES item ('tmp_name', 'name', 'size', 'error')
 * @return array ['success' => bool, 'stored_name' => string|null, 'mime' => string|null, 'size' => int|null, 'message' => string]
 */
function process_image_upload(array $file): array
{
    // 1. Basic error and upload checks
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload parameters.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ['success' => false, 'message' => 'File exceeds maximum upload size (5 MB).'],
            UPLOAD_ERR_NO_FILE => ['success' => false, 'message' => 'No file was uploaded.'],
            default => ['success' => false, 'message' => 'Upload error occurred (code ' . $file['error'] . ').'],
        };
    }

    $tmpPath = $file['tmp_name'];
    if (!is_file($tmpPath)) {
        return ['success' => false, 'message' => 'Temporary upload file not found.'];
    }

    // 2. Size check
    $fileSize = filesize($tmpPath);
    if ($fileSize > UPLOAD_MAX_BYTES) {
        return ['success' => false, 'message' => 'Image exceeds the 5 MB limit.'];
    }

    // 3. MIME inspection via finfo (magic bytes)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!isset(UPLOAD_ALLOWED_MIMES[$detectedMime])) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Only JPG, PNG, and WebP images are permitted.'
        ];
    }

    $ext = UPLOAD_ALLOWED_MIMES[$detectedMime];

    // 4. GD Re-encoding to strip EXIF, GPS, and malicious payloads
    $imageResource = false;
    switch ($detectedMime) {
        case 'image/jpeg':
            $imageResource = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $imageResource = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $imageResource = @imagecreatefromwebp($tmpPath);
            break;
    }

    if (!$imageResource) {
        return [
            'success' => false,
            'message' => 'Could not process image. The file may be corrupted.'
        ];
    }

    // 5. Generate random filename and destination path
    $uploadDir = UPLOAD_PATH;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath   = $uploadDir . '/' . $storedName;

    // Save newly encoded clean image without EXIF metadata
    $saved = false;
    switch ($detectedMime) {
        case 'image/jpeg':
            $saved = imagejpeg($imageResource, $destPath, 88);
            break;
        case 'image/png':
            imagealphablending($imageResource, false);
            imagesavealpha($imageResource, true);
            $saved = imagepng($imageResource, $destPath, 8);
            break;
        case 'image/webp':
            $saved = imagewebp($imageResource, $destPath, 88);
            break;
    }

    imagedestroy($imageResource);

    if (!$saved || !file_exists($destPath)) {
        return ['success' => false, 'message' => 'Failed to save sanitized image to storage.'];
    }

    $finalSize = filesize($destPath);

    return [
        'success'     => true,
        'stored_name' => $storedName,
        'mime'        => $detectedMime,
        'size'        => $finalSize,
        'message'     => 'Image sanitized and saved.',
    ];
}

/**
 * Generate a thumbnail from an existing stored image.
 * 
 * @param string $sourcePath Path to stored original
 * @param int $maxWidth Max thumbnail width (default 320)
 * @param int $maxHeight Max thumbnail height (default 240)
 * @return string|false Binary image string or false on failure
 */
function create_thumbnail(string $sourcePath, int $maxWidth = 320, int $maxHeight = 240): string|false
{
    if (!file_exists($sourcePath)) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        return false;
    }

    $origW = $info[0];
    $origH = $info[1];
    $mime  = $info['mime'];

    // Calculate proportional dimensions
    $ratio = min($maxWidth / $origW, $maxHeight / $origH);
    $thumbW = max(1, (int) round($origW * $ratio));
    $thumbH = max(1, (int) round($origH * $ratio));

    $srcImg = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png'  => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
        default      => false,
    };

    if (!$srcImg) {
        return false;
    }

    $thumbImg = imagecreatetruecolor($thumbW, $thumbH);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
        $transparent = imagecolorallocatealpha($thumbImg, 255, 255, 255, 127);
        imagefilledrectangle($thumbImg, 0, 0, $thumbW, $thumbH, $transparent);
    }

    imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

    ob_start();
    match ($mime) {
        'image/jpeg' => imagejpeg($thumbImg, null, 82),
        'image/png'  => imagepng($thumbImg, null, 7),
        'image/webp' => imagewebp($thumbImg, null, 82),
        default      => imagejpeg($thumbImg, null, 82),
    };
    $data = ob_get_clean();

    imagedestroy($srcImg);
    imagedestroy($thumbImg);

    return $data;
}
