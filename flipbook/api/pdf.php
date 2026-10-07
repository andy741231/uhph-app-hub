<?php
/**
 * PDF viewing endpoint
 * Serves the flipbook PDF with long-term cache headers and range-request support.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$flipbookId = $_GET['id'] ?? null;
$slug       = $_GET['slug'] ?? null;

if (!$flipbookId && !$slug) {
    http_response_code(400);
    echo json_encode(['error' => 'Flipbook ID or slug required']);
    exit;
}

$db = getDB();

if ($flipbookId) {
    $stmt = $db->prepare("SELECT * FROM flipbooks WHERE id = ?");
    $stmt->execute([(int)$flipbookId]);
} else {
    $stmt = $db->prepare("SELECT * FROM flipbooks WHERE slug = ?");
    $stmt->execute([$slug]);
}

$flipbook = $stmt->fetch();

if (!$flipbook) {
    http_response_code(404);
    echo json_encode(['error' => 'Flipbook not found']);
    exit;
}

// Visibility rule: private PDFs are served only to owner/admin.
flipbook_authorize_view($flipbook);

$pdfPath = UPLOAD_DIR . '/' . basename((string)$flipbook['pdf_filename']);
$real = realpath($pdfPath);
if ($real === false || !str_starts_with($real, realpath(UPLOAD_DIR) . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    echo json_encode(['error' => 'PDF file not found']);
    exit;
}
$pdfPath = $real;

$fileSize = filesize($pdfPath);

// Visibility is mutable, so a previously "public" response must never be
// replayable from a cache: no-store for every response, no ETag/304
// conditionals, no far-future Expires. Range support is retained for pdf.js.
header('Content-Type: application/pdf');
header('Cache-Control: no-store');
header('Accept-Ranges: bytes');

// Range request support (used by pdf.js for large PDFs)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
        $start = $matches[1] !== '' ? (int)$matches[1] : 0;
        $end   = $matches[2] !== '' ? (int)$matches[2] : $fileSize - 1;

        if ($start < 0 || $start >= $fileSize || $end < $start) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }

        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        header('HTTP/1.1 206 Partial Content');
        header('Content-Length: ' . $length);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);

        $fp = fopen($pdfPath, 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
        exit;
    }
}

header('Content-Length: ' . $fileSize);
readfile($pdfPath);
exit;
