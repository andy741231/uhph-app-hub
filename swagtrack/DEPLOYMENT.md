# SwagTrack Deployment Guide

## Production Environment: IIS (Windows Server)

Your production server at `https://uhph.uh.edu/apps/swagtrack/` runs **IIS with PHP via FastCGI**.

## Deployment Steps

### 1. Upload Files
Copy the entire `/swagtrack` folder to your production server at:
```
E:\hub\public\apps\swagtrack\
```

### 2. Required Files
Ensure these files are present:
- ✅ `web.config` - IIS URL rewriting configuration (NOT .htaccess)
- ✅ `index.html` - Main application file
- ✅ `config.php` - Airtable API configuration
- ✅ `router.php` - PHP routing (for local dev only)
- ✅ `api/` folder - Backend API
- ✅ `assets/` folder - JavaScript and other assets

### 3. IIS Configuration

The `web.config` file handles URL rewriting for IIS:
- Routes `/apps/swagtrack/api/*` requests to `/apps/swagtrack/api/index.php`
- Sets `index.html` as the default document

### 4. PHP Configuration
Ensure PHP is configured in IIS:
- PHP handler must be enabled
- FastCGI must be configured
- Application pool must allow PHP execution

### 5. Verify Airtable Credentials
Check `config.php` contains the correct:
- Airtable API key
- Base ID
- Table IDs

## How It Works

### Local Development (Apache/PHP Built-in Server)
- Uses `router.php` for routing
- `.htaccess` handles URL rewriting
- Runs at `http://localhost:8001`

### Production (IIS)
- Uses `web.config` for URL rewriting
- IIS FastCGI processes PHP
- Runs at `https://uhph.uh.edu/apps/swagtrack/`

### Dynamic Path Detection
The app automatically detects its base path:
- **Local:** `baseUrl = ''` → calls `/api/inventory`
- **Production:** `baseUrl = '/apps/swagtrack'` → calls `/apps/swagtrack/api/inventory`

## Troubleshooting

### 403 Error - Application Pool Issue
If you see "HTTP Error 403.18 - Forbidden":
1. Verify the application pool exists for `/apps/swagtrack/`
2. Ensure PHP handler is configured correctly
3. Check that `web.config` is in the root of `/apps/swagtrack/`

### API Calls Failing
1. Test API directly: `https://uhph.uh.edu/apps/swagtrack/api/inventory`
2. Check PHP error logs in IIS
3. Verify Airtable API credentials in `config.php`

### Charts Not Showing
1. Clear browser cache
2. Check browser console for JavaScript errors
3. Verify Chart.js CDN is accessible

## No Build Step Required
This is a **vanilla JavaScript application** - no npm, webpack, or build process needed. Just copy and paste the files to production.
