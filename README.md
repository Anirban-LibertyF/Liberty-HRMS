# Liberty Office (PHP + MySQL/MariaDB)

## Run
    .\start.ps1          # starts MariaDB + PHP server -> http://localhost:8000

## Reset database / reload demo data (destroys all data)
    php -c php.ini database/setup.php             # with demo data
    php -c php.ini database/setup.php --no-demo   # only admin + reference data

## Upgrading an existing database without losing data
After every `git pull`, run the migration (safe to repeat, keeps all data):

    php -c php.ini database/migrate.php

It adds new tables and columns (e.g. the per-employee job responsibilities), fixes leave types (Paid Leave 18 days), renames document categories and **creates any missing Offer Letter and
Appointment Letter PDFs** for every employee (those are files in `storage/uploads`, which git does not carry, so they have to be created on each machine; existing letters are kept - use Generate on the employee page to redo one).
`setup.php` also generates them, but it wipes the database first.

## Logins
**Administrator** (turn on the *Login as Administrator* toggle): `liberty.admin@liberty.com` / `Liberty@2008` - the only admin account.

Employees sign in with their ID (`LOE-###`, auto-generated when the admin adds them - the numbers are random, see the Employees page) or email:

| ID | Password | Role |
|---|---|---|
| LOE-001 | Welcome@123 | Manager (Sarah Jenkins) |
| LOE-002 | Welcome@123 | Employee (Alex Morgan) |
| LOE-003 | Welcome@123 | Employee (Marcus Vance) |
| LOE-004 | Welcome@123 | Employee (Priya Nair) |
| LOE-005 | Welcome@123 | Employee (David Kim) |
| LOE-006 | Test@12345 | Test Employee |

## Layout
- `public/`   web root (pages, actions.php, assets)
- `app/`      config, DB helpers, attendance/leave/payroll logic, layout
- `database/` schema.sql + setup.php (seed)
- `storage/`  uploaded files (outside web root, served via download.php with access checks)
- DB settings: `app/config.php` (db `trackflow`, user `trackflow`)

