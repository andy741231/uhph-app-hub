<?php
// cec-events/api/index.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PATCH,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/MySQLService.php';

$config = require __DIR__ . '/config.php';

try {
    $db = new MySQLService($config);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Parse URI to extract resource segments
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts   = array_values(array_filter(explode('/', $uri)));

// Find 'api' segment and extract what follows: events, year, [id]
$apiIdx  = array_search('api', $parts);
$resource = ($apiIdx !== false && isset($parts[$apiIdx + 1])) ? $parts[$apiIdx + 1] : null;
$param1   = ($apiIdx !== false && isset($parts[$apiIdx + 2])) ? $parts[$apiIdx + 2] : null;
$param2   = ($apiIdx !== false && isset($parts[$apiIdx + 3])) ? $parts[$apiIdx + 3] : null;

$method = $_SERVER['REQUEST_METHOD'];

// Fallback for query parameter if path parsing fails or resource is the script name
if (isset($_GET['resource'])) {
    $resource = $_GET['resource'];
    $param1 = $_GET['year'] ?? null;
    $param2 = $_GET['id'] ?? null;
}

try {
    // GET /api/  — return available endpoints (for testing)
    if ($resource === null || $resource === 'index.php') {
        echo json_encode([
            'endpoints' => [
                'GET /api/years' => 'Get available years',
                'GET /api/events/{year}' => 'Get events for a year',
                'POST /api/events/{year}' => 'Create a new event',
                'PATCH /api/events/{year}/{id}' => 'Update an event',
                'POST /api/years/new' => 'Register a new year'
            ]
        ]);
        exit();
    }

    // GET /api/years  — return available years
    if ($resource === 'years' && $method === 'GET') {
        $years = $db->getYears();
        // Return in "tables" format matching what the frontend expected from Airtable metadata
        $tables = array_map(fn($y) => ['name' => "$y Events"], $years);
        echo json_encode(['tables' => $tables]);
        exit();
    }

    // /api/events/{year}[/{id}]
    if ($resource === 'events') {
        $year = $param1;
        $id   = $param2;

        if (!$year) {
            http_response_code(400);
            echo json_encode(['error' => 'Year is required']);
            exit();
        }

        if ($method === 'GET' && !$id) {
            // GET /api/events/{year}  — fetch all events for year
            $records = $db->getEventsByYear($year);
            echo json_encode(['records' => $records]);
            exit();
        }

        if ($method === 'GET' && $id) {
            // GET /api/events/{year}/{id}
            $record = $db->getEventById($id);
            echo json_encode($record);
            exit();
        }

        if ($method === 'POST' && !$id) {
            // POST /api/events/{year}  — create a new event
            $input  = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = $input['fields'] ?? [];
            $record = $db->createEvent($year, $fields);
            http_response_code(201);
            echo json_encode($record);
            exit();
        }

        if ($method === 'PATCH' && $id) {
            // PATCH /api/events/{year}/{id}  — update event
            $input  = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = $input['fields'] ?? [];
            $record = $db->updateEvent($id, $fields);
            echo json_encode($record);
            exit();
        }
    }

    // /api/years/new  — create a new year (MySQL: no-op, year is just a value)
    if ((($resource === 'years' && $param1 === 'new') || $resource === 'years/new') && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $year  = $input['year'] ?? null;
        if (!$year || !is_numeric($year)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid year is required']);
            exit();
        }
        echo json_encode(['success' => true, 'year' => (int)$year]);
        exit();
    }

    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);

} catch (Exception $e) {
    $code = (int)$e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode(['error' => $e->getMessage()]);
}
