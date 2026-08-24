<?php
// swagtrack/api/services/MySQLService.php

class MySQLService {
    private $pdo;

    public function __construct($config) {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, $config['db_username'], $config['db_password'], $options);
    }

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    /** Return all inventory items as Airtable-compatible records. */
    public function getInventory() {
        $stmt = $this->pdo->query("SELECT * FROM inventory ORDER BY item_name ASC");
        return array_map([$this, 'inventoryRowToRecord'], $stmt->fetchAll());
    }

    /** Return a single inventory item by id. */
    public function getInventoryItem($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception("Item not found", 404);
        return $this->inventoryRowToRecord($row);
    }

    /** Create an inventory item. */
    public function createInventoryItem($fields) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO inventory (item_name, description, category, current_stock_quantity, minimum_stock_threshold)
             VALUES (:item_name, :description, :category, :stock, :threshold)"
        );
        $stmt->execute([
            ':item_name'   => $fields['Item Name']                ?? '',
            ':description' => $fields['Description']              ?? '',
            ':category'    => $fields['Category']                 ?? 'Uncategorized',
            ':stock'       => (int)($fields['Current Stock Quantity']    ?? 0),
            ':threshold'   => (int)($fields['Minimum Stock Threshold']   ?? 10),
        ]);
        return $this->getInventoryItem($this->pdo->lastInsertId());
    }

    /** Update stock quantity of an item. */
    public function updateInventoryStock($id, $newStock) {
        $stmt = $this->pdo->prepare(
            "UPDATE inventory SET current_stock_quantity = ? WHERE id = ?"
        );
        $stmt->execute([(int)$newStock, (int)$id]);
        return $this->getInventoryItem($id);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /** Return all transactions (newest first) with item names joined. */
    public function getTransactions() {
        $sql = "SELECT t.*, i.item_name
                FROM transactions t
                LEFT JOIN inventory i ON t.inventory_id = i.id
                ORDER BY t.created_at DESC";
        $stmt = $this->pdo->query($sql);
        return array_map([$this, 'transactionRowToRecord'], $stmt->fetchAll());
    }

    /** Create a transaction (does NOT update stock - caller handles that). */
    public function createTransaction($inventoryId, $type, $quantity, $userName, $notes = '') {
        $stmt = $this->pdo->prepare(
            "INSERT INTO transactions (inventory_id, transaction_type, quantity, user_name, notes)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([(int)$inventoryId, $type, (int)$quantity, $userName, $notes]);
        return (int)$this->pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /** Return all users as Airtable-compatible records. */
    public function getUsers() {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY name ASC");
        return array_map([$this, 'userRowToRecord'], $stmt->fetchAll());
    }

    // -------------------------------------------------------------------------
    // Row converters — keep Airtable-style { id, fields } so the Vue.js frontend
    // doesn't need to change.
    // -------------------------------------------------------------------------

    private function inventoryRowToRecord($row) {
        return [
            'id'     => (string)$row['id'],
            'fields' => [
                'Item Name'                => $row['item_name'],
                'Description'             => $row['description'] ?? '',
                'Category'                => $row['category'] ?? 'Uncategorized',
                'Current Stock Quantity'  => (int)$row['current_stock_quantity'],
                'Minimum Stock Threshold' => (int)$row['minimum_stock_threshold'],
            ],
        ];
    }

    private function transactionRowToRecord($row) {
        return [
            'id'     => (string)$row['id'],
            'fields' => [
                // Frontend uses t.fields['Items'] as an array of IDs
                'Items'            => [(string)$row['inventory_id']],
                'Item Name'        => $row['item_name'] ?? 'Unknown Item',
                'Transaction Type' => $row['transaction_type'],
                'Quantity'         => (int)$row['quantity'],
                'User Name'        => $row['user_name'],
                'Notes'            => $row['notes'] ?? '',
                'Created'          => $row['created_at'],
            ],
        ];
    }

    private function userRowToRecord($row) {
        return [
            'id'     => (string)$row['id'],
            'fields' => [
                'Name'  => $row['name'],
                'Email' => $row['email'] ?? '',
            ],
        ];
    }
}
