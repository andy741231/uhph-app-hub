from flask import Flask, jsonify, request, send_from_directory
import requests
import os
from typing import Dict, Any

app = Flask(__name__, static_folder='static', template_folder='.')

class CECEventsConnector:
    def __init__(self, api_key: str):
        self.api_key = api_key
        self.base_id = "appJvV050bOJ3p3Yw"  # CEC Events base
        self.base_url = "https://api.airtable.com/v0"
        
    def get_table_data(self, table_name: str, limit: int = 100) -> Dict[str, Any]:
        """Fetch records from a specific table"""
        url = f"{self.base_url}/{self.base_id}/{table_name}"
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }
        params = {"maxRecords": limit}
        
        try:
            response = requests.get(url, headers=headers, params=params)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Error fetching data: {e}")
            return {"error": str(e)}

    def update_record(self, table_name: str, record_id: str, fields: Dict[str, Any]) -> Dict[str, Any]:
        """Update a specific record"""
        url = f"{self.base_url}/{self.base_id}/{table_name}/{record_id}"
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }
        data = {
            "fields": fields,
            "typecast": True
        }
        
        try:
            response = requests.patch(url, headers=headers, json=data)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Error updating record: {e}")
            return {"error": str(e)}

# Configuration
API_KEY = "REDACTED_AIRTABLE_TOKEN_B"
connector = CECEventsConnector(API_KEY)

@app.route('/')
def index():
    return send_from_directory('.', 'index.html')

@app.route('/api/events/<year>', methods=['GET'])
def get_events(year):
    table_name = f"{year} Events"
    data = connector.get_table_data(table_name)
    return jsonify(data)

@app.route('/api/events/<year>/<record_id>', methods=['PATCH'])
def update_event(year, record_id):
    table_name = f"{year} Events"
    fields = request.json.get('fields', {})
    result = connector.update_record(table_name, record_id, fields)
    return jsonify(result)

if __name__ == '__main__':
    app.run(debug=True, port=5000)
