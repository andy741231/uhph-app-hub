<?php
// migrate_data.php — import Airtable export into MySQL partners table

$config = [
    'db_host'     => 'uhph-server1.cougarnet.uh.edu',
    'db_port'     => '3306',
    'db_name'     => 'cec-events',
    'db_username' => 'web_app',
    'db_password' => 'UHPH@2025_again',
];

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_username'],
    $config['db_password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Create the partners table if it does not already exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_name VARCHAR(255) NOT NULL,
    org_contact_title VARCHAR(255),
    org_type VARCHAR(255),
    pi VARCHAR(255),
    city VARCHAR(255),
    state VARCHAR(50),
    country VARCHAR(100),
    neighborhood VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Active',
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(500),
    address TEXT,
    postal_code INT,
    mou_status VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_org_name (org_name),
    INDEX idx_pi (pi),
    INDEX idx_status (status),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "Table 'partners' ensured.\n";

// Load Airtable export
$json = file_get_contents(__DIR__ . '/airtable_export.json');
$airtableData = json_decode($json, true);
$records = $airtableData['records'] ?? [];
echo "Records to import: " . count($records) . "\n";

// Airtable field → MySQL column mapping
$fieldMapping = [
    'Org Name '               => 'org_name',
    'Org Contact (and Title)' => 'org_contact_title',
    'Org Type'                => 'org_type',
    'PI'                      => 'pi',
    'City'                    => 'city',
    'State'                   => 'state',
    'Country'                 => 'country',
    'Neighborhood'            => 'neighborhood',
    'Status'                  => 'status',
    'Email'                   => 'email',
    'Phone'                   => 'phone',
    'Website'                 => 'website',
    'Address'                 => 'address',
    'Postal Code'             => 'postal_code',
    'with MOUs/Subaward? '    => 'mou_status',
    'Notes'                   => 'notes',
];

$stmt = $pdo->prepare(
    "INSERT INTO partners (org_name, org_contact_title, org_type, pi, city, state, country,
                          neighborhood, status, email, phone, website, address,
                          postal_code, mou_status, notes)
     VALUES (:org_name, :org_contact_title, :org_type, :pi, :city, :state, :country,
             :neighborhood, :status, :email, :phone, :website, :address,
             :postal_code, :mou_status, :notes)"
);

$count = 0;
$errors = 0;
foreach ($records as $record) {
    $fields = $record['fields'] ?? [];
    $params = [];

    foreach ($fieldMapping as $airtableField => $dbColumn) {
        $val = $fields[$airtableField] ?? null;
        // Trim trailing whitespace/newlines from text values
        if (is_string($val)) {
            $val = trim($val);
            $val = ($val === '') ? null : $val;
        }
        $params[":$dbColumn"] = $val;
    }

    // org_name is required; skip if missing
    if (empty($params[':org_name'])) {
        echo "Skipping record {$record['id']}: missing org_name\n";
        continue;
    }

    try {
        $stmt->execute($params);
        $count++;
    } catch (Exception $e) {
        echo "Error inserting record {$record['id']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "Done. Migrated $count records successfully";
if ($errors) echo ", $errors errors";
echo ".\n";
