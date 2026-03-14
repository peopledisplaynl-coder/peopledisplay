# Changelog

All notable changes to PeopleDisplay are documented here.

## Version 2.0.0 — License System Release (2026-03-09)

### Added
- **License enforcement system** (`includes/license.php`, `includes/license_check.php`)
  - Starter / Professional / Business tier definitions
  - `requireLicense()` — blocks access if no valid license is activated
  - `requireFeature(string $key)` — redirects to upgrade page if tier lacks feature
  - `canAddUser/Employee/Location/Department()` — enforces per-tier count limits
  - `hasFeature(string $key)`, `getTierLimits()` — used for UI gating
- **8-step web installer** (upgraded from 6 steps)
  - Step 2: EULA acceptance (Dutch + English, language toggle)
  - Step 3: License key validation against peopledisplay.nl
  - EULA acceptance recorded in `config` table (`eula_accepted`, `eula_accepted_at`, `eula_version`)
- **Feature gating in admin dashboard**
  - `visitors_manage.php` and `visitor_email_config.php` hidden/grayed when `visitor_management` not in tier
  - `kiosk_tokens_manage.php` hidden/grayed when `kiosk_mode` not in tier
  - "Upgrade" badge with link to pricing page shown in place of locked items
- **Usage limit enforcement**
  - `users_create.php` checks `canAddUser()` before INSERT
  - `employees_manage.php` checks `canAddEmployee()` before INSERT
  - `locations_manage.php` checks `canAddLocation()` before INSERT
  - `afdelingen_manage.php` checks `canAddDepartment()` before INSERT
- **Automatic update check system** (`includes/update_check.php`)
  - Fetches `admin.peopledisplay.nl/updates/version.json` at most once per week
  - Compares remote version to `PEOPLEDISPLAY_VERSION` constant
  - Dismissable banner in admin dashboard (saves dismissed version to DB)
  - `admin/api/dismiss_update.php` — admin-only dismiss endpoint
- **Version constants** (`includes/version.php`)
  - `PEOPLEDISPLAY_VERSION`, `PEOPLEDISPLAY_RELEASE_DATE`
  - `VERSION_CHECK_URL`, `VERSION_CHECK_INTERVAL`, `CHANGELOG_URL`, `DOWNLOAD_URL`
- **Version footer** in admin dashboard — displays current version, changelog link
- **EULA files**: `eula_nl.txt` (Dutch, 11 articles), `eula_en.txt` (English translation)
- **`license_management.php`** — upgrade/license info page linked from locked feature placeholders

### Changed
- Installer expanded from 6 steps to 8 steps (EULA + license added before database setup)
- All admin panel pages now include `license_check.php` after `db.php`
- `visitor_register.php`, `visitor_checkout.php` require `visitor_management` feature
- README updated with license tier pricing and activation instructions

## Version 2.0 — Universal Installer Release (2026-03)

### Added
- **Universal web installer** (6-step wizard, WordPress-style)
  - Automatic PHP and extension version check
  - Database connection test with live feedback
  - Automated schema import (30 tables)
  - Admin account creation with password validation
  - Auto-writes `admin/db_config.php`
  - Installer self-locks after completion
- **Automatic environment detection** (`includes/environment.php`)
  - Detects XAMPP, Strato, cPanel, Plesk, VPS, localhost
  - Configures session path per environment
  - Suppresses `open_basedir` restriction warnings
- **Sub-status system** — extra configurable status buttons (Pause, Working from Home, Vacation)
  - Optional time limit per sub-status (auto-resets at configured time)
  - Per-user customizable button labels
  - Visual card color per sub-status
- **Visitor registration system** — digital visitor logbook
  - Email notifications on visitor arrival
  - Privacy consent tracking
  - Token-based check-in/check-out via email links
  - Multi-day visit support
- **Token-based kiosk check-in** — unattended tablet/kiosk login
- **Audit logging** — full history of employee status changes
- **Online users panel** — see who is currently logged into the admin
- **CSV import/export** for employees
- **Badge/ID card generation** (PDF via TCPDF)
- **BHV print module** — emergency roster printout
- **WiFi auto check-in** — detect location by IP range
- **Name display options** — full name, first name, last name, initial + last name
- **Sort toggle** — sort by first name, last name, or status (IN first)
- **PWA support** — installable as app on mobile/tablet, offline page
- **Presentation mode** — auto-start Google Slides after configurable idle period
- **Backup/restore** functionality in admin panel
- **Role-based access**: superadmin, admin, user
- **Per-user feature access control** — enable/disable features per user

### Fixed
- **Status updates now persist** across page navigation
  - Root cause: `WHERE (id = ? OR employee_id = ?)` caused MySQL strict mode error 1292
    when comparing VARCHAR employee ID with INT `id` column
  - Fix: use `WHERE employee_id = ?` exclusively
- **PHP warnings no longer corrupt API JSON responses**
  - `open_basedir` restriction warnings from `file_exists('/usr/local/cpanel')`
    and `file_exists('/usr/local/psa')` now suppressed with `@`
  - `error_reporting(0)` added at top of all API files
- **UI cards no longer freeze after status update**
  - Root cause: `applyCurrentFilters()` called inside async fetch callbacks
    destroyed and recreated DOM, making `card` reference stale; disabled buttons
    could never be re-enabled
  - Fix: removed `applyCurrentFilters()` from fetch callbacks; update card CSS
    class directly on valid `card` reference instead
- **Audit log HTTP 500** — `employee_audit` table has `created_at` column,
  not `changed_at`; all queries updated
- **Redirect loop on dashboard** — `session_start()` called before `db.php`
  in some pages; custom session path now only set for XAMPP/localhost
- **BASE_PATH hardcoded** — replaced with dynamic detection from URL in `app.js`
  and `bhv-print/bhv-print.js`
- **PRG pattern** added to employee/location/department manage pages
  to prevent form re-submission on F5 refresh

### Changed
- Database connection now uses unified PDO via `includes/db.php`
- Session handling is environment-aware (no custom path on production)
- API responses always return clean JSON (no PHP warnings prepended)
- Employee status update uses `employee_id` (VARCHAR) not `id` (INT)

### Removed
- Hardcoded client-specific images and logos
- Database credentials from version control
- Debug scripts, test files, and development-only code
- Backup ZIP files and CSV exports containing client data
- Duplicate/legacy admin scripts
- Development analysis and debug documentation
- Duplicate icon directory (`/icons/` — kept `/images/icons/`)
- Migration scripts (superseded by clean `install.sql`)

## Version 1.0 — Original Release

Initial release. Single-customer deployment on Strato hosting.
Features: basic employee IN/OUT tracking, admin panel, multi-location support.
