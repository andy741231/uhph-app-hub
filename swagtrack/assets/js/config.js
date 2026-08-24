// API configuration for SwagTrack
// The app now uses a PHP/MySQL backend instead of Airtable.
// Detect the base path dynamically for both localhost and IIS
const API_BASE = window.location.pathname.replace(/\/[^/]*$/, '');
