# ZK Attendance (zk)

This repository contains two main components used to collect and view attendance data from ZKTeco devices:

1. Python services (root): device sync and a small API server that expose attendance rows.
2. Laravel-based Dashboard (in `dashboard/`): UI to view, edit and manage attendance summaries.

This README summarizes the project layout, how to run each component, and notes for troubleshooting.

## Repository layout (top-level)

- `api_server.py` - Small Flask API that exposes attendance data from the configured MySQL DB (token protected).
- `device_sync.py` - Polls the ZKTeco device and inserts attendance records into MySQL.
- `config.py` - Shared config for the Python services (DB and device connection settings).
- `create_db_.py`, `restore_from_log.py` - helper scripts related to DB creation/restore.
- `req.txt` - Python dependencies for the services (Flask, pymysql, zk, etc.).
- `dashboard/` - Laravel application (dashboard UI).
- `logs/` - runtime logs for Python services.

The Laravel app is self-contained in `dashboard/` and holds the web UI and API routes used by the dashboard.

## Quick architecture summary

- The Python `device_sync.py` connects to a ZKTeco device and writes attendance rows into a MySQL table (`attendances`).
- `api_server.py` reads the same DB and can return a formatted JSON list (requires an Authorization Bearer token found in `config.py`).
- The Laravel `dashboard/` app reads attendance rows (via Eloquent/DB queries) and provides a DataTables-backed UI with features:
  - Daily/Monthly/User filters
  - Edit and Delete actions (UI + backend endpoints)
  - Simple session-based login (single password from env: `DASHBOARD_PASSWORD`, default `secret`)
  - Client-side "Copy" (TSV to clipboard) and "Export" (CSV download) for filtered data (no backend call required)

Notes: some server-side export attempts were experimented with, but the dashboard copy/export functionality now runs entirely on the client (no backend changes required).

## Requirements

System-level:
- Python 3.8+
- MySQL server accessible from the app
- PHP 8.0+ (Laravel requirements depend on your installed composer packages)
- Composer (for PHP dependencies)

Python dependencies (install in a venv):

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r req.txt
```

Laravel app dependencies (from `dashboard/`):

```bash
cd dashboard
composer install
npm install   # optional if you plan to build frontend assets
cp .env.example .env
# set DB and other config in .env
php artisan key:generate
php artisan migrate   # if migrations exist and you want to create DB tables
php artisan serve --host=0.0.0.0 --port=8081
```

## Configs and environment

- Python services use `config.py`. Edit those constants for device IP, DB connection, API token and port.
- Laravel dashboard uses `.env` inside `dashboard/`. You can also set an env var `DASHBOARD_PASSWORD` to change the login password (defaults to `secret`).

Important DB table: `attendances` (used by both Python scripts and the Laravel app). Ensure your schema matches the code expectations (columns: `user_id`, `timestamp`, `status`, `punch`, `message`, `created_at`, `updated_at`).

## How to run components

Device sync (poll the device):

```bash
# (from repo root)
python3 -m venv .venv
source .venv/bin/activate
pip install -r req.txt
python device_sync.py
```

API server (Flask) for read-only listing (token-protected):

```bash
source .venv/bin/activate
python api_server.py
# send requests with header: Authorization: Bearer <API_TOKEN> (see config.py)
```

Dashboard (Laravel):

```bash
cd dashboard
composer install
cp .env.example .env
# configure DB details in .env
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8081
# Visit http://127.0.0.1:8081/ and login (password from DASHBOARD_PASSWORD env or 'secret')
```

## Dashboard features (what's implemented)

- Server-side DataTables powered listing (server-side pagination, search)
- Filters: Daily (date picker), Monthly (month picker), User-wise
- Edit/Delete actions (use UI to edit first/last punch, punch and status; delete removes records for that user-date)
- Simple auth: session-based login that checks `DASHBOARD_PASSWORD` env var
- Copy: copies all filtered rows from DataTable to clipboard (TSV) so you can paste into Excel
- Export: downloads a CSV of all filtered rows (client-side) — opens in Excel/Sheets

Notes:
- Both Copy and Export are client-side and use the visible/filtered data from the DataTable. No server-side export is required now.


## Security & next steps

- Current auth is a single password stored in env — suitable for small private deployments only. Consider implementing Laravel's auth scaffolding for full user management.
- For production, serve the Laravel app with proper webserver (Nginx/Apache), enable HTTPS, and secure database credentials.
- Consider adding unit tests and database migrations/schema files for reproducibility.
