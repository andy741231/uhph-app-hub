<?php
// swagtrack/api/index.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/services/MySQLService.php';

$config = require __DIR__ . '/../config.php';

try {
    $db = new MySQLService($config);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Service initialization failed: " . $e->getMessage()]);
    exit();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($uri, '/'));

// Find 'api' in the path and get the next segment
$apiIndex = array_search('api', $pathParts);
$resource = ($apiIndex !== false && isset($pathParts[$apiIndex + 1])) ? $pathParts[$apiIndex + 1] : null;
$recordId  = ($apiIndex !== false && isset($pathParts[$apiIndex + 2])) ? $pathParts[$apiIndex + 2] : null;

// Fallback for query parameter if path parsing fails or resource is the script name
if (isset($_GET['resource'])) {
    $resource = $_GET['resource'];
}

$requestMethod = $_SERVER["REQUEST_METHOD"];

try {
    // GET /api/  — return available endpoints (for testing)
    if ($resource === null || $resource === 'index.php') {
        echo json_encode([
            'endpoints' => [
                'GET /api/inventory' => 'Get all inventory items',
                'POST /api/inventory' => 'Create a new inventory item',
                'GET /api/transactions' => 'Get all transactions',
                'POST /api/transactions' => 'Create a new transaction',
                'GET /api/users' => 'Get all users'
            ]
        ]);
        exit();
    }

    switch ($resource) {
        case 'inventory':
            handleInventory($requestMethod, $db, $recordId);
            break;
        case 'transactions':
            handleTransactions($requestMethod, $db);
            break;
        case 'users':
            handleUsers($requestMethod, $db);
            break;
        default:
            http_response_code(404);
            echo json_encode(["error" => "Resource not found"]);
            break;
    }
} catch (Exception $e) {
    $code = (int)$e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode(["error" => $e->getMessage(), "file" => $e->getFile(), "line" => $e->getLine()]);
}

// -----------------------------------------------------------------------------
// Handlers
// -----------------------------------------------------------------------------

function handleInventory($method, MySQLService $db, $recordId) {
    if ($method === 'GET') {
        $records = $db->getInventory();
        echo json_encode(["records" => $records]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['name'])) {
            http_response_code(400);
            echo json_encode(["error" => "Item name is required"]);
            return;
        }

        $fields = [
            'Item Name'                => $input['name'],
            'Description'             => $input['description'] ?? '',
            'Category'                => $input['category'] ?? 'Uncategorized',
            'Current Stock Quantity'  => (int)($input['stock']     ?? 0),
            'Minimum Stock Threshold' => (int)($input['threshold'] ?? 10),
        ];

        $record = $db->createInventoryItem($fields);
        echo json_encode($record);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
}

function handleUsers($method, MySQLService $db) {
    if ($method === 'GET') {
        $records = $db->getUsers();
        echo json_encode(["records" => $records]);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
}

function handleTransactions($method, MySQLService $db) {
    if ($method === 'GET') {
        $records = $db->getTransactions();
        echo json_encode(["records" => $records]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);

        // Validation
        if (!isset($input['itemId']) || !isset($input['type']) || !isset($input['quantity']) || !isset($input['user'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required fields"]);
            return;
        }

        $itemId   = (int)$input['itemId'];
        $type     = $input['type'];   // 'Check In', 'Check Out', 'Restock'
        $quantity = (int)$input['quantity'];
        $user     = is_array($input['user']) ? implode(', ', $input['user']) : $input['user'];
        $notes    = $input['notes'] ?? '';

        // Get current item state
        $item         = $db->getInventoryItem($itemId);
        $currentStock = $item['fields']['Current Stock Quantity'] ?? 0;

        // Check stock availability for Check Out
        if ($type === 'Check Out') {
            if ($quantity > $currentStock) {
                http_response_code(400);
                echo json_encode(["error" => "Insufficient stock. Current: $currentStock"]);
                return;
            }
            $newStock = $currentStock - $quantity;
        } else {
            // Check In or Restock
            $newStock = $currentStock + $quantity;
        }

        // Create transaction
        $db->createTransaction($itemId, $type, $quantity, $user, $notes);

        // Update inventory stock
        $db->updateInventoryStock($itemId, $newStock);

        echo json_encode([
            "message"  => "Transaction successful",
            "newStock" => $newStock
        ]);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
}
