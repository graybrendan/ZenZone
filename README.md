# ZenZone

ZenZone is a PHP/MySQL web app focused on mindfulness, self-awareness, goal tracking, and performance support.

## Current stack
- PHP
- MySQL
- XAMPP
- phpMyAdmin
- GitHub
- VS Code

## Local project path
`C:\xampp\htdocs\ZenZone`

## Current purpose
ZenZone is being developed as a capstone project. The app is intended to help users:
- complete a baseline assessment
- check in regularly
- track goals
- reflect on progress
- interact with guided support features

## Current project structure
- `public/` - user-facing pages and route entry points
- `api/` - backend handlers and form/action processing
- `includes/` - shared config, DB, auth, validation, session, and helper logic
- `sql/` - database schema and seed files
- `docs/` - project documentation

## Main feature areas
- Authentication
- Baseline assessment
- Daily / voluntary check-ins
- Goal creation and goal priority logic
- Reflections
- Coach features
- Content / lessons

## Local setup
1. Start Apache and MySQL in XAMPP.
2. Make sure the project folder is inside:
   `C:\xampp\htdocs\ZenZone`
3. Create or import the local database in phpMyAdmin.
4. Use the SQL files in `sql/` as needed.
5. Confirm local config in `includes/config.php`.

## Current local config
ZenZone currently uses a local config pattern like:
- DB host: `127.0.0.1`
- DB name: `zenzone`
- DB user: `root`
- DB password: empty in local development
- Base URL: `/ZenZone/public`

## Important notes
- This project is currently optimized for local XAMPP development.
- Keep solutions simple and consistent with the existing PHP/MySQL structure.
- Avoid introducing new frameworks unless clearly necessary.
- Coach retrieval integration notes are in `docs/coach-rag.md`.

## Development workflow
Before starting work:
```powershell
git pull
```

## Running migrations
For fresh installs, import `sql/schema.sql`.

For existing databases, apply the needed files in `sql/migrations/` in date/name order.
Current security-related migrations include:
- `sql/migrations/add_auth_tokens.sql`
- `sql/migrations/2026-04-23_001_login_rate_limits.sql`

Local (XAMPP / phpMyAdmin):
1. Open phpMyAdmin and select the `zenzone` database.
2. Go to the `SQL` tab, paste each migration file (in order), and run it.
3. Confirm new tables appear (for example `auth_tokens`, `login_rate_limits`).

Railway (production database):
1. Open your Railway project and open the database SQL console.
2. Paste each migration file (in order) and execute.
3. Confirm new tables exist (for example `auth_tokens`, `login_rate_limits`).
