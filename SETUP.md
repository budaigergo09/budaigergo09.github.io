# PDC Darts Simulator - Server Setup Guide

## Requirements

- **PHP 7.4+** with SQLite3 extension enabled
- Web server (Apache/Nginx) with URL rewriting support
- Write permissions for the `/api/data/` directory

## Quick Setup (git pull)

If you already have the server running with PHP:

1. **Git pull the latest code:**
   ```bash
   git pull
   ```

2. **Ensure the `/api/data/` directory is writable:**
   ```bash
   mkdir -p api/data
   chmod 755 api/data
   ```

3. **Access the Admin Panel:**
   - Go to `https://yoursite.com/admin.html`
   

4. **Import Default Data:**
   - In the admin panel, go to **Import Data** section
   - Click **Import Tournaments** to load all season tournaments
   - Click **Import Players** to load all players

5. **Done!** The game will now load players and tournaments from the database.

## First-Time Server Setup

### 1. PHP Requirements

Make sure PHP has SQLite3 enabled. Check with:
```bash
php -m | grep sqlite
```

If not enabled, install it:
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3

# CentOS/RHEL
sudo yum install php-pdo
```

### 2. Apache Configuration

If using Apache, ensure `.htaccess` is allowed and mod_rewrite is enabled:

```apache
# In your Apache config or .htaccess in /api/
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /api/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]
</IfModule>
```

### 3. Nginx Configuration

If using Nginx:

```nginx
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}
```

### 4. File Permissions

```bash
# Make sure the data directory exists and is writable
mkdir -p api/data
chmod 755 api/data
chown www-data:www-data api/data  # Adjust user based on your setup
```

### 5. Security Recommendations

1. **Change the JWT secret** in `/api/index.php`:
   ```php
   define('JWT_SECRET', 'your-unique-secret-key-here');
   ```

2. **Change admin credentials** in `/admin.html`:
   ```javascript
   const ADMIN_USER = 'your-admin-username';
   const ADMIN_PASS = 'your-secure-password';
   ```

3. **Restrict admin panel access** if possible (e.g., IP whitelist)

## API Endpoints

### Public Endpoints (no auth required)
- `GET /api/players` - Get all players
- `GET /api/tournaments` - Get all tournaments

### Admin Endpoints
- `GET /api/admin/stats` - Dashboard statistics
- `GET /api/admin/tournaments` - List all tournaments
- `POST /api/admin/tournaments` - Add tournament
- `PUT /api/admin/tournaments/{id}` - Update tournament
- `DELETE /api/admin/tournaments/{id}` - Delete tournament
- `POST /api/admin/tournaments/import` - Bulk import tournaments
- `DELETE /api/admin/tournaments/clear` - Clear all tournaments
- `GET /api/admin/players` - List all players
- `POST /api/admin/players` - Add player
- `PUT /api/admin/players/{id}` - Update player
- `DELETE /api/admin/players/{id}` - Delete player
- `POST /api/admin/players/import` - Bulk import players
- `DELETE /api/admin/players/clear` - Clear all players
- `GET /api/admin/users` - List all users
- `DELETE /api/admin/users/{id}` - Delete user

## Database Location

The SQLite database is stored at:
```
/api/data/pdc_darts.db
```

## How Data Loading Works

1. When the game loads (`darts.html`), it tries to fetch players and tournaments from the database API
2. If the database is empty or API is unavailable, it falls back to the hardcoded defaults
3. Use the Admin Panel to import the default data into the database for editing

## Troubleshooting

### "Failed to load tournaments/players"
- Check if PHP is running and SQLite3 is enabled
- Verify the `/api/data/` directory is writable
- Check browser console for CORS errors

### "Database locked"
- This can happen with concurrent writes
- The SQLite database handles this automatically, but if persistent, restart PHP

### Admin panel won't login
- Default credentials are: **root** / **admin**
- Credentials are checked client-side (no API auth for admin)

## Version History

- **v1.2.6** - Database-driven tournaments and players, admin panel management
- **v1.2.5** - Bug fixes for Winmau Masters and WC qualifiers
