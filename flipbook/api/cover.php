<?php
/**
 * Cover image endpoint
 * Serves a flipbook's DB-recorded thumbnail only after the visibility rule
 * passes, since the uploads directory is not directly web-accessible.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$flipbookId = $_GET['id'] ?? null;
if (!$flipbookId) {
    jsonResponse(['error' => 'Flipbook ID required'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM flipbooks WHERE id = ?");
$stmt->execute([(int)$flipbookId]);
$flipbook = $stmt->fetch();
if (!$flipbook) {
    jsonResponse(['error' => 'Flipbook not found'], 404);
}

flipbook_authorize_view($flipbook);

$thumbnail = $flipbook['thumbnail'] ?? null;
$filename = is_string($thumbnail) ? basename($thumbnail) : '';
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
if ($filename === '' || !isset($types[$ext])) {
    jsonResponse(['error' => 'Cover not found'], 404);
}

$path = UPLOAD_DIR . '/' . $filename;
$real = realpath($path);
if ($real === false || !str_starts_with($real, realpath(UPLOAD_DIR) . DIRECTORY_SEPARATOR)) {
    jsonResponse(['error' => 'Cover not found'], 404);
}

// Visibility is mutable — never allow a previously public cover to be
// replayed from a cache after the flipbook becomes private.
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($real));
header('Cache-Control: no-store');
readfile($real);
exit;
