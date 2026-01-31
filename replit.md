# PDC Darts Simulator

## Overview
A web-based PDC darts simulator game that allows users to play single matches, run tournaments, and manage complete seasons. Features player management, tournament configuration, and an admin panel for data management.

## Project Architecture
- **Language**: PHP 8.2
- **Database**: SQLite (stored in `/api/data/pdc_darts.db`)
- **Frontend**: Static HTML files (`darts.html`, `admin.html`)
- **Backend API**: `/api/index.php` - REST API for players, tournaments, and user management

## File Structure
- `darts.html` - Main game interface
- `admin.html` - Admin panel for managing players and tournaments
- `server.php` - PHP router that serves static files and routes API requests
- `api/index.php` - Backend API handling all data operations
- `api/data/` - SQLite database storage directory
- Various `.png`, `.mp3` files - Game assets and sounds

## Running the Project
The application runs on PHP's built-in development server:
```bash
php -S 0.0.0.0:5000 server.php
```

## API Endpoints
- `GET /api/players` - Get all players
- `GET /api/tournaments` - Get all tournaments
- Admin endpoints require authentication (default: root/admin)

## Initial Setup
1. Access the admin panel at `/admin.html`
2. Use the Import Data section to load tournaments and players into the database
3. Return to the main game at `/darts.html`

## Recent Changes
- 2026-01-31: Configured for Replit environment with custom PHP router (`server.php`)
