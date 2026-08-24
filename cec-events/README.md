# CEC Events Manager

A modern interface for managing CEC Community Engagement Events stored in Airtable.

## Setup

**No backend required!** This application now uses direct JavaScript API calls to Airtable.

## Running the Application

1. **Simple HTTP Server**: Since we're making direct API calls, you just need a simple web server to serve the static files.

### Option 1: Python's built-in server
```bash
python3 -m http.server 8000
```

### Option 2: Node.js serve (if you have Node.js)
```bash
npx serve .
```

### Option 3: Live Server in VS Code
- Use the Live Server extension
- Right-click `index.html` and select "Open with Live Server"

2. **Open your browser** to: `http://localhost:8000` (or whatever port your server uses)

## Features

- **Event Dashboard**: View events by year (2026, 2025, 2024).
- **Modern UI**: Clean, responsive interface using CSS variables and modern design principles.
- **Edit Capability**: Click any event card to edit its details (Date, Location, Status, etc.).
- **Real-time Updates**: Changes are saved directly to Airtable via JavaScript API calls.
- **Status Tracking**: Visual indicators for Tentative, Ready, and Completed events.
- **Smart Dropdowns**: Auto-populate with existing choices from your Airtable data.
- **Time Picker**: Native HTML time input for better time selection.
- **Custom Options**: Add new event types, demographics, and attendees on the fly.

## Security Note

This application uses direct frontend API calls to Airtable, which means:
- ✅ **Simpler setup** - No backend server needed
- ✅ **Faster development** - Just open in browser
- ⚠️ **API key exposure** - The API key is visible in the frontend code
- ⚠️ **Best for internal tools** - Not recommended for public applications

## Project Structure

- `index.html`: Main dashboard interface (now standalone)
- `static/css/styles.css`: Custom styling with design system variables
- `static/js/app.js`: Frontend logic with direct Airtable API calls
- `server.py`: **No longer needed** - kept for reference only

## API Configuration

The Airtable API configuration is now in `static/js/app.js`:
```javascript
apiConfig: {
    apiKey: 'REDACTED_AIRTABLE_TOKEN_B',
    baseId: 'appJvV050bOJ3p3Yw',
    baseUrl: 'https://api.airtable.com/v0'
}
```