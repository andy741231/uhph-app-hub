# Airtable Manager (Vue 3 + PHP)

A minimal app to view, create, edit, and delete Airtable records using a PHP backend proxy (to keep your Airtable token safe) and a Vue 3 frontend.

## Features
- List tables in a Base (Airtable Metadata API)
- List records with optional `view`, `filterByFormula`, pagination
- Create, update, and delete records
- Clean dark UI with inline editing

## Project Structure
- `backend/api.php` – PHP proxy to Airtable REST API
- `backend/config.php.example` – Copy to `backend/config.php` and set token or use environment variable
- `frontend/index.html` – Vue 3 SPA (CDN build) using Axios

## Prerequisites
- PHP 7.4+ with cURL extension (most systems have this by default)
- An Airtable Personal Access Token (PAT) with the right scopes

## Setup
1. Token configuration (choose ONE of the following):
   - Set environment variable before starting PHP:
     - macOS/Linux: `export AIRTABLE_TOKEN="pat_..."`
     - Windows (PowerShell): `$env:AIRTABLE_TOKEN="pat_..."`
   - OR copy `backend/config.php.example` to `backend/config.php` and set `AIRTABLE_TOKEN` inside it.

   Note: Do not commit real tokens to version control.

2. Start the PHP server (from the project root):
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```
   This will serve both `frontend/` and `backend/` over http://127.0.0.1:8000

3. Open the app:
   - Navigate to http://127.0.0.1:8000/frontend/index.html in your browser.

4. Use the UI:
   - Enter your Airtable Base ID (e.g., `appXXXXXXXXXXXXXX`).
   - Click "Load Tables" to fetch the list of tables.
   - Select a table, optionally specify a view or formula filter.
   - Click "Load Records" to view and edit.
   - Create records by entering JSON for `fields` (e.g., `{ "Name": "New item" }`).

## Backend API
All requests go through `backend/api.php` with `action` query param.

- `GET api.php?action=listTables&baseId=BASE_ID`
- `GET api.php?action=listRecords&baseId=BASE_ID&table=TABLE_NAME&view=VIEW&filterByFormula=FORMULA&pageSize=50&offset=...`
- `POST api.php?action=createRecord&baseId=BASE_ID&table=TABLE_NAME`
  - JSON body: `{ "fields": { "Name": "Foo" } }`
- `PATCH api.php?action=updateRecord&baseId=BASE_ID&table=TABLE_NAME&id=RECORD_ID`
  - JSON body: `{ "fields": { "Name": "Bar" } }`
- `DELETE api.php?action=deleteRecord&baseId=BASE_ID&table=TABLE_NAME&id=RECORD_ID`

CORS is open (`*`) by default and can be restricted via `CORS_ALLOW_ORIGIN` in `backend/config.php`.

## Security Notes
- Your Airtable PAT is only used server-side by `backend/api.php`. Do not expose it to the browser.
- Limit scopes on the PAT to the minimum needed (e.g., read or read/write for specific bases).
- Consider restricting origins with `CORS_ALLOW_ORIGIN` in production.

## Troubleshooting
- 401/403 errors: Verify the PAT and scopes, and that the base/table names are correct.
- 422/400 on updates/creates: Ensure fields match your Airtable schema (names and types).
- Network errors: Confirm PHP server is running and reachable from the browser.

## Notes
- You provided a token reference:
  - Token name: `uhph-readOnly`
  - Token id: `pathuIlkt4elFMHDS`

  Please provide the actual Airtable PAT value (starts with `pat`), or set it as `AIRTABLE_TOKEN` so the backend can authenticate.

## License
MIT
