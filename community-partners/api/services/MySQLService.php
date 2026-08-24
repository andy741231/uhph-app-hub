<?php
// community-partners/api/services/MySQLService.php

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

    /**
     * Convert a MySQL row to the Airtable-compatible record format the frontend expects.
     * The id field becomes the record id; all other fields go into a "fields" object
     * using the same Airtable field names the frontend already knows.
     */
    private function rowToRecord($row) {
        return [
            'id'     => (string)$row['id'],
            'fields' => [
                'Org Name '                  => $row['org_name'] ?? '',
                'Org Contact (and Title)'    => $row['org_contact_title'] ?? '',
                'Org Type'                   => $row['org_type'] ?? '',
                'PI'                         => $row['pi'] ?? '',
                'City'                       => $row['city'] ?? '',
                'State'                      => $row['state'] ?? '',
                'Country'                    => $row['country'] ?? '',
                'Neighborhood'               => $row['neighborhood'] ?? '',
                'Status'                     => $row['status'] ?? '',
                'Email'                      => $row['email'] ?? '',
                'Phone'                      => $row['phone'] ?? '',
                'Website'                    => $row['website'] ?? '',
                'Address'                    => $row['address'] ?? '',
                'Postal Code'                => $row['postal_code'] ? (int)$row['postal_code'] : null,
                'with MOUs/Subaward? '       => $row['mou_status'] ?? '',
                'Notes'                      => $row['notes'] ?? '',
            ],
        ];
    }

    /** Return all partners as Airtable-style records. */
    public function getPartners() {
        $stmt = $this->pdo->query("SELECT * FROM partners ORDER BY org_name ASC");
        $rows = $stmt->fetchAll();
        return array_map([$this, 'rowToRecord'], $rows);
    }

    /** Return a single partner by id. */
    public function getPartnerById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM partners WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new Exception("Partner not found", 404);
        }
        return $this->rowToRecord($row);
    }

    /** Create a new partner; returns the created record. */
    public function createPartner($fields) {
        $params = $this->fieldsToParams($fields);

        $sql = "INSERT INTO partners
                    (org_name, org_contact_title, org_type, pi, city, state, country,
                     neighborhood, status, email, phone, website, address, postal_code, mou_status, notes)
                VALUES
                    (:org_name, :org_contact_title, :org_type, :pi, :city, :state, :country,
                     :neighborhood, :status, :email, :phone, :website, :address, :postal_code, :mou_status, :notes)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $newId = $this->pdo->lastInsertId();
        return $this->getPartnerById($newId);
    }

    /** Update an existing partner; returns the updated record. */
    public function updatePartner($id, $fields) {
        $map = $this->fieldNameToColumn();
        $setParts = [];
        $params = [':id' => (int)$id];

        foreach ($fields as $fieldName => $value) {
            if (isset($map[$fieldName])) {
                $col = $map[$fieldName];
                $setParts[] = "`$col` = :$col";
                $params[":$col"] = ($value === '' ? null : $value);
            }
        }

        if (empty($setParts)) {
            return $this->getPartnerById($id);
        }

        $sql = "UPDATE partners SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->getPartnerById($id);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function fieldNameToColumn() {
        return [
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
    }

    private function fieldsToParams($fields) {
        $map = $this->fieldNameToColumn();
        $defaults = array_fill_keys(array_values($map), null);

        foreach ($fields as $fieldName => $value) {
            if (isset($map[$fieldName])) {
                $defaults[$map[$fieldName]] = ($value === '' ? null : $value);
            }
        }

        $params = [];
        foreach ($defaults as $col => $val) {
            $params[":$col"] = $val;
        }
        return $params;
    }
}
