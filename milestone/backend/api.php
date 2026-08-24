<?php
// backend/api.php
// Simple PHP proxy for Airtable REST API to keep the PAT secret on the server.
// Endpoints (action param):
// - listRecords: GET baseId, table (name or ID), optional: view, filterByFormula, pageSize, offset
// - createRecord: POST baseId, table, fields{}
// - updateRecord: PATCH baseId, table, id, fields{}
// - deleteRecord: DELETE baseId, table, id
// - listTables: GET baseId (uses Metadata API)
// - getRecord: GET baseId, table, id
//
// Auth: Read token from env AIRTABLE_TOKEN or config.php constant AIRTABLE_TOKEN
// CORS: Allow origin based on CORS_ALLOW_ORIGIN constant or default '*'

header('Content-Type: application/json');

// Load config if present
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$token = getenv('AIRTABLE_TOKEN');
if (!$token && defined('AIRTABLE_TOKEN')) {
    $token = AIRTABLE_TOKEN;
}

if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'Server not configured: missing AIRTABLE_TOKEN. Set env var or backend/config.php']);
    exit;
}

// CORS handling
$allowOrigin = defined('CORS_ALLOW_ORIGIN') ? CORS_ALLOW_ORIGIN : '*';
header('Access-Control-Allow-Origin: ' . $allowOrigin);
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

function input_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function http_json($url, $method, $token, $body = null) {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $proxyUrl = getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY');
    if (!$proxyUrl && defined('HTTP_PROXY_URL')) {
        $proxyUrl = HTTP_PROXY_URL;
    }
    if ($proxyUrl) {
        curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);

        $proxyAuth = getenv('HTTPS_PROXY_USERPWD') ?: getenv('HTTP_PROXY_USERPWD');
        if (!$proxyAuth && defined('HTTP_PROXY_USERPWD')) {
            $proxyAuth = HTTP_PROXY_USERPWD;
        }
        if ($proxyAuth) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
        }
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$status, $resp, $err];
}

function safe_param($name, $required = true) {
    $val = $_GET[$name] ?? $_POST[$name] ?? null;
    if ($required && ($val === null || $val === '')) {
        http_response_code(400);
        echo json_encode(['error' => "Missing parameter: $name"]);
        exit;
    }
    return $val;
}

try {
    switch ($action) {
        case 'listRecords': {
            if ($method !== 'GET') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $table = safe_param('table');

            $query = [];
            $optional = ['view', 'filterByFormula', 'pageSize', 'offset', 'sort'];
            foreach ($optional as $k) {
                if (isset($_GET[$k]) && $_GET[$k] !== '') {
                    $query[$k] = $_GET[$k];
                }
            }
            $queryString = http_build_query($query);
            $url = "https://api.airtable.com/v0/" . rawurlencode($baseId) . "/" . rawurlencode($table);
            if ($queryString) { $url .= '?' . $queryString; }

            [$status, $resp, $err] = http_json($url, 'GET', $token);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        case 'createRecord': {
            if ($method !== 'POST') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $table = safe_param('table');
            $payload = input_json();
            if (!isset($payload['fields']) || !is_array($payload['fields'])) {
                throw new Exception('Missing fields{} in JSON body', 400);
            }
            $body = ['fields' => $payload['fields']];
            $url = "https://api.airtable.com/v0/" . rawurlencode($baseId) . "/" . rawurlencode($table);
            [$status, $resp, $err] = http_json($url, 'POST', $token, $body);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        case 'updateRecord': {
            if ($method !== 'PATCH') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $table = safe_param('table');
            $id = safe_param('id');
            $payload = input_json();
            if (!isset($payload['fields']) || !is_array($payload['fields'])) {
                throw new Exception('Missing fields{} in JSON body', 400);
            }
            $body = ['fields' => $payload['fields']];
            $url = "https://api.airtable.com/v0/" . rawurlencode($baseId) . "/" . rawurlencode($table) . "/" . rawurlencode($id);
            [$status, $resp, $err] = http_json($url, 'PATCH', $token, $body);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        case 'deleteRecord': {
            if ($method !== 'DELETE') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $table = safe_param('table');
            $id = safe_param('id');
            $url = "https://api.airtable.com/v0/" . rawurlencode($baseId) . "/" . rawurlencode($table) . "/" . rawurlencode($id);
            [$status, $resp, $err] = http_json($url, 'DELETE', $token);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        case 'listBases': {
            if ($method !== 'GET') { throw new Exception('Method not allowed', 405); }
            $workspaceId = safe_param('workspaceId');

            $bases = [];
            $baseUrl = "https://api.airtable.com/v0/meta/bases?workspaceIds%5B%5D=" . rawurlencode($workspaceId);
            $offsetParam = null;

            do {
                $requestUrl = $baseUrl;
                if ($offsetParam) {
                    $requestUrl .= '&offset=' . rawurlencode($offsetParam);
                }

                [$status, $resp, $err] = http_json($requestUrl, 'GET', $token);
                if ($err) { throw new Exception($err, 500); }
                if ($status !== 200) {
                    http_response_code($status);
                    echo $resp;
                    break 2;
                }

                $payload = json_decode($resp, true);
                if (!is_array($payload)) { throw new Exception('Invalid response from Airtable', 500); }

                $list = $payload['bases'] ?? [];
                foreach ($list as $base) {
                    if (!is_array($base)) { continue; }
                    $bases[] = [
                        'id' => $base['id'] ?? '',
                        'name' => $base['name'] ?? '',
                        'workspaceId' => $base['workspaceId'] ?? $workspaceId
                    ];
                }

                $offsetParam = $payload['offset'] ?? null;
            } while ($offsetParam);

            http_response_code(200);
            echo json_encode(['bases' => $bases]);
            break;
        }
        case 'listTables': {
            if ($method !== 'GET') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $url = "https://api.airtable.com/v0/meta/bases/" . rawurlencode($baseId) . "/tables";
            [$status, $resp, $err] = http_json($url, 'GET', $token);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        case 'getRecord': {
            if ($method !== 'GET') { throw new Exception('Method not allowed', 405); }
            $baseId = safe_param('baseId');
            $table = safe_param('table');
            $id = safe_param('id');
            $url = "https://api.airtable.com/v0/" . rawurlencode($baseId) . "/" . rawurlencode($table) . "/" . rawurlencode($id);
            [$status, $resp, $err] = http_json($url, 'GET', $token);
            if ($err) { throw new Exception($err, 500); }
            http_response_code($status);
            echo $resp;
            break;
        }
        default: {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown or missing action']);
        }
    }
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 400 || $code > 599) { $code = 500; }
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}
