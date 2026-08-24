<?php
/**
 * PDF viewing endpoint
 * Serves the flipbook PDF with long-term cache headers and range-request support.
 */
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

$pdfPath = UPLOAD_DIR . '/' . $flipbook['pdf_filename'];
if (!file_exists($pdfPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'PDF file not found']);
    exit;
}

$fileSize = filesize($pdfPath);
$lastModified = filemtime($pdfPath);
$etag = '"' . md5($flipbook['pdf_filename'] . '-' . $fileSize . '-' . $lastModified) . '"';

// Respond to conditional requests
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// Long-term cache headers: unique filename per upload means immutable
header('Content-Type: application/pdf');
header('Cache-Control: public, max-age=31536000, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+1 year')) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);
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
