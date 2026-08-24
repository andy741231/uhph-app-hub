# Community Partners Migration: Airtable to MySQL

## Overview
Migrate the Community Partners application from Airtable to MySQL, following the same workflow used for cec-events and swagtrack.

## Prerequisites
- MySQL access to uhph-server1.cougarnet.uh.edu
- Airtable API access to existing data
- PHP backend setup (already configured on IIS)

## Step 1: Inspect Airtable Schema

### Get Airtable Configuration
From `static/js/app.js`:
- Base ID: `app8RNDG23B8JApj0`
- Table ID: `tbleHSQb8Fo62nM9K`
- API Key: `REDACTED_AIRTABLE_TOKEN_B`

### Extract Field Names from JavaScript
From the app.js code, the following fields are used:
- `Org Name ` (note trailing space)
- `Org Contact (and Title)`
- `Org Type`
- `PI`
- `City`
- `State`
- `Country`
- `Neighborhood`
- `Status`
- `Email`
- `Phone`
- `Website`
- `Address`
- `Notes`

### Fetch Sample Data from Airtable
```bash
curl -X GET "https://api.airtable.com/v0/app8RNDG23B8JApj0/tbleHSQb8Fo62nM9K" \
  -H "Authorization: Bearer REDACTED_AIRTABLE_TOKEN_B" \
  -H "Content-Type: application/json"
```

## Step 2: Create MySQL Table in Existing Database

### Use Existing Database
The partners table will be created in the existing `cec-events` database.

### Create Table
```sql
USE `cec-events`;

CREATE TABLE partners (
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
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_org_name (org_name),
    INDEX idx_pi (pi),
    INDEX idx_status (status),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Step 3: Migrate Data from Airtable to MySQL

### Export Data from Airtable
Use the Airtable API to fetch all records:
```bash
# Get all records (may need pagination if >100 records)
curl -X GET "https://api.airtable.com/v0/app8RNDG23B8JApj0/tbleHSQb8Fo62nM9K" \
  -H "Authorization: Bearer REDACTED_AIRTABLE_TOKEN_B" \
  -H "Content-Type: application/json" > airtable_export.json
```

### Create Migration Script
Create `migrate_data.php` to import the data:
```php
<?php
// migrate_data.php

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

// Load Airtable export
$airtableData = json_decode(file_get_contents('airtable_export.json'), true);
$records = $airtableData['records'] ?? [];

$fieldMapping = [
    'Org Name ' => 'org_name',
    'Org Contact (and Title)' => 'org_contact_title',
    'Org Type' => 'org_type',
    'PI' => 'pi',
    'City' => 'city',
    'State' => 'state',
    'Country' => 'country',
    'Neighborhood' => 'neighborhood',
    'Status' => 'status',
    'Email' => 'email',
    'Phone' => 'phone',
    'Website' => 'website',
    'Address' => 'address',
    'Notes' => 'notes',
];

$stmt = $pdo->prepare(
    "INSERT INTO partners (org_name, org_contact_title, org_type, pi, city, state, country, 
                          neighborhood, status, email, phone, website, address, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$count = 0;
foreach ($records as $record) {
    $fields = $record['fields'] ?? [];
    
    $params = [];
    foreach ($fieldMapping as $airtableField => $dbColumn) {
        $params[] = $fields[$airtableField] ?? null;
    }
    
    try {
        $stmt->execute($params);
        $count++;
    } catch (Exception $e) {
        echo "Error inserting record {$record['id']}: " . $e->getMessage() . "\n";
    }
}

echo "Migrated $count records successfully\n";
```

### Run Migration
```bash
php migrate_data.php
```

## Step 4: Create PHP Backend

### Create Directory Structure
```
community-partners/
├── api/
│   ├── config.php
│   ├── index.php
│   └── services/
│       └── MySQLService.php
├── web.config
└── router.php
```

### Create api/config.php
```php
<?php
return [
    'db_host'     => 'uhph-server1.cougarnet.uh.edu',
    'db_port'     => '3306',
    'db_name'     => 'cec-events',
    'db_username' => 'web_app',
    'db_password' => 'UHPH@2025_again',
];
```

### Create api/services/MySQLService.php
Copy from cec-events and adapt field names:
- Map Airtable field names to MySQL columns
- Implement CRUD operations for partners table
- Return Airtable-compatible `{id, fields}` format

### Create api/index.php
Copy from cec-events and adapt:
- Endpoints: GET/POST/PATCH for partners
- Query parameter routing: `?resource=partners`

### Create web.config
```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
    </system.webServer>
</configuration>
```

### Create router.php
```php
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

if (strpos($path, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    return;
}

require __DIR__ . '/index.html';
```

## Step 5: Update Frontend JavaScript

### Update static/js/app.js
1. Replace Airtable config with dynamic base path:
```javascript
apiConfig: {
    baseUrl: window.location.pathname.replace(/\/[^/]*$/, '')
}
```

2. Replace Airtable API calls with PHP API calls:
- `fetchPartners()`: Call `/api/index.php?resource=partners`
- `savePartner()`: Call `/api/index.php?resource=partners` with POST/PATCH

3. Update field name mapping to match MySQL columns

### Update static/js/dashboard.js
Same changes as app.js for consistency

### Create assets/js/config.js (if needed)
```javascript
const API_BASE = window.location.pathname.replace(/\/[^/]*$/, '');
```

## Step 6: Test and Deploy

### Local Testing
```bash
cd community-partners
php -S localhost:8002 -t . router.php
```

Test:
- `http://localhost:8002/api/index.php?resource=partners`
- `http://localhost:8002/`

### IIS Deployment
1. Upload entire folder to `E:\apps\community-partners\`
2. Test: `https://uhph.uh.edu/apps/community-partners/`
3. Test API: `https://uhph.uh.edu/apps/community-partners/api/index.php?resource=partners`

## Step 7: Verification Checklist

- [ ] MySQL database `community-partners` created
- [ ] Partners table created with correct schema
- [ ] Data migrated from Airtable to MySQL
- [ ] PHP backend created and tested
- [ ] Frontend JavaScript updated to call PHP API
- [ ] Local testing successful
- [ ] IIS deployment successful
- [ ] All CRUD operations working (Create, Read, Update, Delete)
- [ ] Search and filter functionality working
- [ ] Dashboard charts working (if applicable)

## Key Differences from cec-events

1. **Single table**: No year-based table structure (unlike cec-events)
2. **Field names**: Different field structure (partners vs events)
3. **No year dropdown**: Simpler navigation without year selection
4. **Status field**: May have different status values

## Troubleshooting

### Data Migration Issues
- Check field name mapping matches Airtable exactly
- Handle trailing spaces in field names (e.g., "Org Name ")
- Handle null values and empty strings

### API Issues
- Verify MySQL connection credentials
- Check web.config doesn't conflict with parent IIS config
- Use query parameters instead of URL rewrite if needed

### Frontend Issues
- Clear browser cache after deployment
- Check console for API call URLs
- Verify dynamic base path detection works on IIS
