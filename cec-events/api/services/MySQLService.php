<?php
// cec-events/api/services/MySQLService.php

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
                'Event Name'                                        => $row['event_name'] ?? '',
                'Event Date'                                        => $row['event_date'] ?? '',
                ' Time'                                             => $row['event_time'] ?? '',
                'Type of Event'                                     => $row['type_of_event'] ?? '',
                'Neighborhood'                                      => $row['neighborhood'] ?? '',
                'Address'                                           => $row['address'] ?? '',
                'Event Location'                                    => $row['event_location'] ?? '',
                'Status (Tentative, Ready, Completed)'              => $row['status'] ?? 'Tentative',
                'Demographic Served'                                => $row['demographic_served'] ?? '',
                'Attendees'                                         => $row['attendees'] ?? '',
                '# of Interactions'                                 => $row['interactions'] ?? '',
                'Equipment Needed'                                  => $row['equipment_needed'] ?? '',
                'Notes'                                             => $row['notes'] ?? '',
                'Focus Challenges/Highlights'                       => $row['focus_challenges_highlights'] ?? '',
                'New Contacts Made'                                 => $row['new_contacts_made'] ?? '',
                'Existing contacts engaged'                         => $row['existing_contacts_engaged'] ?? '',
                'Potential Future CEC engagement opportunities'     => $row['potential_future_cec'] ?? '',
                'Possible Alignment with researcher'                => $row['possible_alignment_researcher'] ?? '',
            ],
        ];
    }

    /** Return distinct years available in the events table (descending). */
    public function getYears() {
        $stmt = $this->pdo->query("SELECT DISTINCT year FROM events ORDER BY year DESC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Return all events for a given year as Airtable-style records. */
    public function getEventsByYear($year) {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE year = ? ORDER BY event_date ASC");
        $stmt->execute([(int)$year]);
        $rows = $stmt->fetchAll();
        return array_map([$this, 'rowToRecord'], $rows);
    }

    /** Return a single event by id. */
    public function getEventById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new Exception("Event not found", 404);
        }
        return $this->rowToRecord($row);
    }

    /** Create a new event; returns the created record. */
    public function createEvent($year, $fields) {
        $sql = "INSERT INTO events
                    (year, event_name, event_date, event_time, type_of_event, neighborhood, address,
                     event_location, status, demographic_served, attendees, interactions,
                     equipment_needed, notes, focus_challenges_highlights, new_contacts_made,
                     existing_contacts_engaged, potential_future_cec, possible_alignment_researcher)
                VALUES
                    (:year, :event_name, :event_date, :event_time, :type_of_event, :neighborhood, :address,
                     :event_location, :status, :demographic_served, :attendees, :interactions,
                     :equipment_needed, :notes, :focus_challenges_highlights, :new_contacts_made,
                     :existing_contacts_engaged, :potential_future_cec, :possible_alignment_researcher)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->fieldsToParams($year, $fields));
        $newId = $this->pdo->lastInsertId();
        return $this->getEventById($newId);
    }

    /** Update an existing event; returns the updated record. */
    public function updateEvent($id, $fields) {
        // Build SET clause dynamically based on what's provided
        $map = $this->fieldNameToColumn();
        $setParts = [];
        $params = [':id' => (int)$id];

        foreach ($fields as $fieldName => $value) {
            if (isset($map[$fieldName])) {
                $col = $map[$fieldName];
                $setParts[] = "`$col` = :$col";
                $params[":$col"] = $value === '' ? null : $value;
            }
        }

        if (empty($setParts)) {
            return $this->getEventById($id);
        }

        $sql = "UPDATE events SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->getEventById($id);
    }

    /** Ensure a year exists (MySQL has a single table; this is a no-op, returns true). */
    public function ensureYear($year) {
        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function fieldNameToColumn() {
        return [
            'Event Name'                                       => 'event_name',
            'Event Date'                                       => 'event_date',
            ' Time'                                            => 'event_time',
            'Type of Event'                                    => 'type_of_event',
            'Neighborhood'                                     => 'neighborhood',
            'Address'                                          => 'address',
            'Event Location'                                   => 'event_location',
            'Status (Tentative, Ready, Completed)'             => 'status',
            'Demographic Served'                               => 'demographic_served',
            'Attendees'                                        => 'attendees',
            '# of Interactions'                               => 'interactions',
            'Equipment Needed'                                 => 'equipment_needed',
            'Notes'                                            => 'notes',
            'Focus Challenges/Highlights'                      => 'focus_challenges_highlights',
            'New Contacts Made'                                => 'new_contacts_made',
            'Existing contacts engaged'                        => 'existing_contacts_engaged',
            'Potential Future CEC engagement opportunities'    => 'potential_future_cec',
            'Possible Alignment with researcher'               => 'possible_alignment_researcher',
        ];
    }

    private function fieldsToParams($year, $fields) {
        $map = $this->fieldNameToColumn();
        $defaults = array_fill_keys(array_values($map), null);

        foreach ($fields as $fieldName => $value) {
            if (isset($map[$fieldName])) {
                $defaults[$map[$fieldName]] = $value === '' ? null : $value;
            }
        }

        $params = [':year' => (int)$year];
        foreach ($defaults as $col => $val) {
            $params[":$col"] = $val;
        }
        return $params;
    }
}
