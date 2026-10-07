# Flipbook App

A web-based PDF flipbook viewer with realistic page-turning effects, search, table of contents navigation, clickable links, YouTube video overlays, and embeddable sharing.

## Features

- **PDF Upload** — Upload PDF files and view them as interactive flipbooks
- **Realistic Page Flip** — Smooth page-turning animation with 3D perspective (StPageFlip)
- **Text Search** — Full-text search across all pages with highlighted results
- **Table of Contents** — Auto-extracted from PDF bookmarks/outlines with click-to-navigate
- **Clickable Links** — PDF hyperlinks (internal & external) remain functional
- **Page Flip Sound** — Realistic paper-flip sound via Web Audio API (toggleable)
- **YouTube Video Overlay** — Place YouTube videos on specific pages with custom positioning
- **Edit Mode** — In-viewer editing with drag-and-drop video repositioning
- **Video Management** — Add, delete, and reposition videos directly in the viewer
- **PDF Download** — Download original PDF with one click
- **Embed Code** — Generate iframe embed code to share flipbooks on other websites
- **Responsive** — Works on desktop and mobile

## Tech Stack

- **Backend:** PHP 7.4+, MySQL
- **Frontend:** Vanilla JS (ES6+), PDF.js, StPageFlip, Web Audio API
- **Styling:** Custom CSS (no framework dependency)
- **Server:** IIS on Windows Server 2022 (also works with Apache)

## Access & Visibility

Authentication is Hub SSO (`admin` / `user` application roles). Anonymous
visitors land on a public gallery (`index.php`); signed-in users get a
dashboard (`admin` sees every flipbook, `user` sees only their own uploads).

| Visibility | Gallery | View | Embed |
| --- | --- | --- | --- |
| `public` | Listed | Anyone | Allowed |
| `unlisted` | Not listed | Anyone with the link | Allowed |
| `private` (default) | Not listed | Owner + admin only | Disabled |

All checks run server-side (`flipbook_can_view` / `flipbook_can_manage` in
`includes/auth.php`) on every data path — including `api/pdf.php`,
`api/download.php`, page-text search, and child-overlay endpoints. Uploaded
files are never served directly: `uploads/` is blocked in `web.config`
(IIS hidden segment), `.htaccess` (Apache), and the dev router; PDFs and
cover thumbnails are served through `api/pdf.php` and `api/cover.php`, which
authorize the request first (private content is sent `Cache-Control:
private, no-store` and embeds render an inline notice rather than a
redirect inside the iframe).

### Ownership migration

Existing databases need the ownership/visibility columns:

```bash
php scripts/migrate-ownership.php           # dry run (default)
php scripts/migrate-ownership.php --apply   # apply
```

The runner is CLI-only and idempotent: it adds the columns only when
`owner_subject` is absent, then always runs the NULL-guarded backfill
(`sql/migrations/2026-10-07-ownership-visibility.sql`).

Rollback: revert the application code first and take a database backup;
only then may the four added columns/indexes be dropped. Do **not** run a
destructive rollback while the new code is deployed — readers would lose
access controls.

## Installation

### 1. Database Setup

Run the SQL schema to create the database and tables:

```sql
mysql -u web_app -p < sql/schema.sql
```

Or execute `sql/schema.sql` manually in your MySQL client.

### 2. Configuration

Edit `config.php` to match your environment:

```php
define('BASE_PATH', '/apps/flipbook');  // Your deployment path
define('DB_HOST', 'your-db-host');
define('DB_NAME', 'flipbook');
define('DB_USER', 'your-db-user');
define('DB_PASS', 'your-db-password');
```

### 3. File Permissions

Ensure the `uploads/` directory is writable by the web server:

```bash
chmod 755 uploads/
```

On IIS, grant write permissions to the IIS_IUSRS group for the `uploads` folder.

### 4. PHP Configuration

For large PDF uploads, ensure these PHP settings:

```ini
upload_max_filesize = 100M
post_max_size = 105M
max_execution_time = 300
```

On IIS, also set `maxAllowedContentLength` in `web.config` (already configured).

### 5. Deploy

Copy all files to your IIS site under `/apps/flipbook` (or your configured `BASE_PATH`).

## Project Structure

```
flipbook/
├── config.php              # App configuration (BASE_PATH, DB, uploads)
├── index.php               # Dashboard — list all flipbooks
├── upload.php              # Upload page — create new flipbook from PDF
├── viewer.php              # Flipbook viewer — the main reading experience
├── editor.php              # Editor — manage metadata & video overlays
├── web.config              # IIS configuration
├── .htaccess               # Apache fallback configuration
├── api/
│   ├── cover.php           # Cover thumbnail endpoint (visibility-authorized)
│   ├── upload.php          # PDF upload endpoint
│   ├── flipbooks.php       # CRUD operations for flipbooks
│   ├── text.php            # Save/search extracted text
│   └── videos.php          # Manage YouTube video overlays
├── assets/
│   ├── css/
│   │   ├── app.css         # Global styles
│   │   └── viewer.css      # Viewer-specific styles
│   └── js/
│       ├── viewer.js       # Core flipbook viewer logic
│       └── flip-sound.js   # Web Audio API page-flip sound
├── includes/
│   ├── db.php              # Database connection helper
│   ├── header.php          # Shared HTML header
│   └── footer.php          # Shared HTML footer
├── sql/
│   └── schema.sql          # Database schema
└── uploads/                # Uploaded PDF storage
```

## Usage

1. **Upload a PDF**: Go to the upload page and drag-and-drop a PDF file
2. **View Flipbook**: Click on a flipbook from the dashboard to view it
3. **Navigate**: Use arrow keys, navigation buttons, or click page edges to flip pages
4. **Search**: Press Ctrl+F or click the search icon to search within the document
5. **Table of Contents**: Click the TOC icon to view and navigate bookmarks
6. **Edit Mode**: Click the edit icon in the viewer to enable edit mode
   - Click "Add Video to Current Page" to add a YouTube video overlay
   - Drag videos to reposition them on the page
   - Click the X button on a video to delete it
   - Changes are saved automatically
7. **Download PDF**: Click the download icon to download the original PDF
8. **Embed**: Click the embed button to get iframe code for sharing

## Keyboard Shortcuts (Viewer)

| Key | Action |
|-----|--------|
| `←` / `→` | Previous / Next page |
| `Home` / `End` | First / Last page |
| `F` | Toggle fullscreen |
| `Ctrl+F` | Open search |
| `Esc` | Close panels |
