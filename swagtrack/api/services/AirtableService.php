<?php

class AirtableService {
    private $apiKey;
    private $baseId;
    private $tables;

    public function __construct($config) {
        $this->apiKey = $config['airtable_api_key'];
        $this->baseId = $config['airtable_base_id'];
        $this->tables = $config['tables'];
    }

    private function request($method, $endpoint, $data = null) {
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$endpoint}";
        
        $ch = curl_init($url);
        $headers = [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Airtable API Error: " . $response);
        }

        return json_decode($response, true);
    }

    public function getRecords($tableName, $params = []) {
        $tableId = $this->tables[$tableName] ?? $tableName;
        $queryString = http_build_query($params);
        $endpoint = "{$tableId}?{$queryString}";
        return $this->request('GET', $endpoint);
    }

    public function getRecord($tableName, $recordId) {
        $tableId = $this->tables[$tableName] ?? $tableName;
        return $this->request('GET', "{$tableId}/{$recordId}");
    }

    public function createRecord($tableName, $fields) {
        $tableId = $this->tables[$tableName] ?? $tableName;
        $data = ['fields' => $fields, 'typecast' => true];
        return $this->request('POST', $tableId, $data);
    }

    public function updateRecord($tableName, $recordId, $fields) {
        $tableId = $this->tables[$tableName] ?? $tableName;
        $data = ['fields' => $fields, 'typecast' => true];
        return $this->request('PATCH', "{$tableId}/{$recordId}", $data);
    }
}
