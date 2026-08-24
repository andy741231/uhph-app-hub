import requests
import json

API_KEY = "REDACTED_AIRTABLE_TOKEN_B"
BASE_ID = "appJvV050bOJ3p3Yw"

def create_table_full_schema():
    url = f"https://api.airtable.com/v0/meta/bases/{BASE_ID}/tables"
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json"
    }
    
    # Schema copied from app.js
    data = {
        "name": "2027 Events Test",
        "fields": [
            { "name": "Event Name", "type": "singleLineText" },
            { "name": "Event Date", "type": "date", "options": { "dateFormat": "Local" } },
            { "name": " Time", "type": "singleLineText" },
            { "name": "Type of Event", "type": "singleSelect", "options": { "choices": [
                { "name": "Community Event" },
                { "name": "Neighborhood Association Meeting" },
                { "name": "Health Fair" },
                { "name": "Workshop" }
            ]}},
            { "name": "Event Location", "type": "singleLineText" },
            { "name": "Status (Tentative, Ready, Completed)", "type": "singleSelect", "options": { "choices": [
                { "name": "Tentative", "color": "yellowLight2" },
                { "name": "Ready", "color": "tealLight2" },
                { "name": "Completed", "color": "grayLight2" }
            ]}},
            { "name": "Demographic Served", "type": "singleSelect", "options": { "choices": [
                { "name": "Seniors" },
                { "name": "Families" },
                { "name": "Youth" },
                { "name": "Adults" }
            ]}},
            { "name": "Attendees", "type": "singleSelect", "options": { "choices": [
                { "name": "Nallely Cheung" },
                { "name": "Maria Rodriguez" }
            ]}},
            { "name": "# of Interactions", "type": "singleLineText" },
            { "name": "Equipment Needed", "type": "multilineText" },
            { "name": "Notes", "type": "multilineText" }
        ]
    }
    
    try:
        response = requests.post(url, headers=headers, json=data)
        print(f"Status Code: {response.status_code}")
        print(f"Response: {response.text}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    create_table_full_schema()