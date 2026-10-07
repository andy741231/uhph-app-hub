<?php
/**
 * Flipbooks API
 * GET: List all flipbooks or get one by id/slug
 * PUT: Update flipbook metadata
 * DELETE: Delete a flipbook
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    flipbook_require_api_user();
    flipbook_require_csrf();
}
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// Never expose owner identity to callers who cannot manage the row.
function flipbook_strip_owner(array $row): array
{
    unset($row['owner_email'], $row['owner_subject']);

    return $row;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id']) || isset($_GET['slug'])) {
            // Get single flipbook by ID or slug, then apply the visibility rule.
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM flipbooks WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
            } else {
                $stmt = $db->prepare("SELECT * FROM flipbooks WHERE slug = ?");
                $stmt->execute([$_GET['slug']]);
            }
            $flipbook = $stmt->fetch();
            if (!$flipbook) {
                jsonResponse(['error' => 'Flipbook not found'], 404);
            }
            flipbook_authorize_view($flipbook);
            if (!flipbook_can_manage($flipbook)) {
                $flipbook = flipbook_strip_owner($flipbook);
            }
            // Get videos for this flipbook
            $vstmt = $db->prepare("SELECT * FROM flipbook_videos WHERE flipbook_id = ? ORDER BY page_number");
            $vstmt->execute([$flipbook['id']]);
            $flipbook['videos'] = $vstmt->fetchAll();
            jsonResponse($flipbook);

        } else {
            // List: anonymous -> public only; signed-in user -> own; admin -> all.
            // ?scope=public forces the public gallery for any caller.
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $user = flipbook_current_user();
            $publicOnly = isset($_GET['scope']) && $_GET['scope'] === 'public';

            $where = '';
            $params = [];
            $stripOwner = false;
            if ($publicOnly || ($user === null && FLIPBOOK_HUB_SSO_ENABLED)) {
                $where = " WHERE visibility = 'public'";
                $stripOwner = true;
            } elseif ($user !== null && $user['role'] !== 'admin') {
                $where = " WHERE owner_subject = ?";
                $params[] = $user['subject'];
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM flipbooks" . $where);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $db->prepare("SELECT * FROM flipbooks" . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $flipbooks = $stmt->fetchAll();
            if ($stripOwner) {
                $flipbooks = array_map('flipbook_strip_owner', $flipbooks);
            }

            jsonResponse([
                'flipbooks' => $flipbooks,
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ]);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['id'])) {
            jsonResponse(['error' => 'Flipbook ID required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM flipbooks WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        $flipbook = $stmt->fetch();
        if (!$flipbook) {
            jsonResponse(['error' => 'Flipbook not found'], 404);
        }
        flipbook_authorize_manage($flipbook);

        $fields = [];
        $params = [];

        if (isset($input['title'])) {
            $fields[] = "title = ?";
            $params[] = trim($input['title']);
        }
        if (isset($input['description'])) {
            $fields[] = "description = ?";
            $params[] = trim($input['description']);
        }
        if (isset($input['toc_json'])) {
            $fields[] = "toc_json = ?";
            $params[] = $input['toc_json'] === null ? null : (string)$input['toc_json'];
        }
        if (isset($input['page_count'])) {
            $fields[] = "page_count = ?";
            $params[] = (int)$input['page_count'];
        }
        if (array_key_exists('visibility', $input)) {
            if (!in_array($input['visibility'], ['public', 'unlisted', 'private'], true)) {
                jsonResponse(['error' => 'visibility must be public, unlisted, or private'], 400);
            }
            $fields[] = "visibility = ?";
            $params[] = $input['visibility'];
        }
        if (array_key_exists('settings_json', $input)) {
            $fields[] = "settings_json = ?";
            $val = $input['settings_json'];
            // Accept either a JSON string or an object/array; store NULL when empty.
            if ($val === null || $val === '' || $val === 'null') {
                $params[] = null;
            } elseif (is_string($val)) {
                // Validate it parses as JSON
                $decoded = json_decode($val, true);
                $params[] = ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? null : (string)$val;
            } else {
                $params[] = json_encode($val);
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = (int)$input['id'];
        $sql = "UPDATE flipbooks SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['id'])) {
            jsonResponse(['error' => 'Flipbook ID required'], 400);
        }

        // Get flipbook to check ownership and delete its file
        $stmt = $db->prepare("SELECT * FROM flipbooks WHERE id = ?");
        $stmt->execute([(int)$input['id']]);
        $flipbook = $stmt->fetch();
        if ($flipbook) {
            flipbook_authorize_manage($flipbook);
        }

        if ($flipbook) {
            // Delete PDF file
            $filePath = UPLOAD_DIR . '/' . $flipbook['pdf_filename'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            // Delete from DB (cascades to pages and videos)
            $stmt = $db->prepare("DELETE FROM flipbooks WHERE id = ?");
            $stmt->execute([(int)$input['id']]);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
