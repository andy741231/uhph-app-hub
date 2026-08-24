<?php
// community-partners/api/index.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PATCH,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/services/MySQLService.php';

$config = require __DIR__ . '/config.php';

try {
    $db = new MySQLService($config);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Parse URI to extract resource and optional id
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = array_values(array_filter(explode('/', $uri)));

$apiIdx   = array_search('api', $parts);
$resource = ($apiIdx !== false && isset($parts[$apiIdx + 1])) ? $parts[$apiIdx + 1] : null;
$id       = ($apiIdx !== false && isset($parts[$apiIdx + 2])) ? $parts[$apiIdx + 2] : null;

$method = $_SERVER['REQUEST_METHOD'];

// Fallback for query parameter routing (e.g. ?resource=partners&id=5)
if (isset($_GET['resource'])) {
    $resource = $_GET['resource'];
    $id       = $_GET['id'] ?? null;
}

try {
    // GET /api/  — return available endpoints
    if ($resource === null || $resource === 'index.php') {
        echo json_encode([
            'endpoints' => [
                'GET /api/partners'        => 'Get all partners',
                'POST /api/partners'       => 'Create a new partner',
                'PATCH /api/partners/{id}' => 'Update a partner',
            ]
        ]);
        exit();
    }

    // /api/partners[/{id}]
    if ($resource === 'partners') {

        if ($method === 'GET' && !$id) {
            // GET /api/partners — fetch all
            $records = $db->getPartners();
            echo json_encode(['records' => $records]);
            exit();
        }

        if ($method === 'GET' && $id) {
            // GET /api/partners/{id}
            $record = $db->getPartnerById($id);
            echo json_encode($record);
            exit();
        }

        if ($method === 'POST' && !$id) {
            // POST /api/partners — create
            $input  = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = $input['fields'] ?? [];
            $record = $db->createPartner($fields);
            http_response_code(201);
            echo json_encode($record);
            exit();
        }

        if ($method === 'PATCH' && $id) {
            // PATCH /api/partners/{id} — update
            $input  = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = $input['fields'] ?? [];
            $record = $db->updatePartner($id, $fields);
            echo json_encode($record);
            exit();
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);

} catch (Exception $e) {
    $code = (int)$e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode(['error' => $e->getMessage()]);
}
