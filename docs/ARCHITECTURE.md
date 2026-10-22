# PeopleDisplay — Architecture

PeopleDisplay is a classic multi-page PHP application with vanilla JavaScript, built
for shared hosting: every request is a plain PHP file, state lives in MySQL/MariaDB
and the PHP session, and background work runs from cron scripts. There is no build
step, framework or package manager on the server; `tcpdf/` is vendored.

## Request flow

```
Browser / PWA / kiosk screen
   │
   ├─ page  (index.php, overzicht.php, inuitbord.php, admin/*.php …)
   │     includes/db.php            → $db (PDO), session, environment, remember-me login
   │     includes/license_check.php → licence + tier limits (requireLicense / requireFeature)
   │     admin/auth_helper.php      → role checks for admin pages
   │     includes/migrations.php    → schema upgrades (admin/superadmin page loads only)
   │
   └─ fetch() from app.js / overzicht.js / …  →  admin/api/*.php  or  api/*.php  → JSON
```

- `includes/db.php` reads credentials from `admin/db_config.php` (written by the installer,
  never in git) and exposes one PDO connection as `$db`.
- Roles (`users.role`): `user`, `employee_manager`, `user_manager`, `admin`, `superadmin`.
  SuperAdmin can restrict what an admin may do per feature (`admin/user_permissions.php`).
- Per-user screen features are flags in the JSON column `users.features`
  (e.g. `inuitBord`, `sorteerFunctie`, `sorteerStandaard`, `zoomFunctie`, `zoomStandaard`),
  managed in `admin/users_manage.php` / `admin/features_manage.php`.

## Main screens

| File | Purpose |
|---|---|
| `login.php`, `logout.php`, `reset_password.php` | sign-in (with remember-me tokens) |
| `kiosk_login.php` | automatic sign-in for kiosk screens via a kiosk token |
| `frontpage.php` | start screen with navigation buttons |
| `index.php` + `app.js`, `style.css` | the card check-in screen (IN/OUT, sub-status, location pin, filters, sort, zoom, BHV) |
| `inuitbord.php` | IN/UIT board display mode (two columns, auto-refresh) |
| `overzicht.php` + `overzicht.js` | presence overview |
| `scan.php` | badge/QR scan check-in |
| `visitor_register.php`, `visitor_checkin.php`, `visitor_checkout.php` | visitor registration |
| `bhv-print/` | printable BHV (emergency response) overview |
| `user/profile.php` | own profile, photo, password |
| `admin/dashboard.php` | admin home: tiles, update banner, migrations |
| `admin/*_manage.php` | employees, users, locations, departments, visitors, kiosk tokens, badges, features, config |
| `admin/backup.php`, `restore.php` | backups; full restore to a new domain/host |
| `activate_license.php`, `admin/license_management.php` | licence and continuity-key activation |
| `install.php` (+ `install.sql`) | 8-step installer in the root |
| `install/` | 6-step installer wizard (uses `install/sql/install.sql`, a copy of `install.sql`) |

PWA: `manifest.json`, `service-worker.js` (never caches PHP, `app.js` or `style.css`),
`pwa-init.js`, `pwa-installer.js`, `ios-install-prompt-fixed.js`, `offline.html`.
`api/app-version.php` lets open screens notice a new version and reload.

## API endpoints

All return JSON and check the session/role themselves.

### `admin/api/` — admin and management

| Endpoint | Purpose |
|---|---|
| `employees_api.php` | employee list and status changes (server-side checkout to `home_locatie`) |
| `employee_add.php`, `employee_update.php`, `employee_delete.php` | employee CRUD |
| `import_employees_csv.php`, `export_employees_csv.php` | CSV import/export |
| `bulk_actions_api.php` | bulk status/field changes |
| `update_status.php` | set an employee's status |
| `users_list.php`, `users_create.php`, `users_update.php`, `users_delete.php`, `user_delete.php`, `users_password.php` | user management |
| `force_logout_user.php`, `get_online_users.php` | online users and forced logout |
| `features.php`, `features_update.php`, `user_features.php`, `get-user-features.php` | per-user feature flags |
| `afdelingen_api.php` | departments |
| `get_locations_ordered.php`, `save_locations_order.php`, `sync_locations_smart.php` | locations and their order |
| `get_button_config.php`, `save_user_button_names.php`, `check_button_names_api.php` | button labels / status buttons |
| `kiosk_token_actions.php` | kiosk tokens |
| `inuitbord-api.php` | data for the IN/UIT board |
| `get-config.php` | configuration for screens |
| `change_password.php`, `update_profile.php`, `upload_profile_photo.php`, `delete_profile_photo.php` | own profile |
| `changelog.php`, `dismiss_update.php` | update banner and changelog |
| `proxy.php` | server-side proxy for external requests |

### `api/` — screens, kiosk, visitors

| Endpoint | Purpose |
|---|---|
| `app-version.php` | current version for auto-reload |
| `heartbeat.php`, `check_session.php`, `check_session_status.php` | session liveness |
| `get_all_employees.php`, `get_employee.php`, `get_employees_by_location.php` | employee data for screens |
| `get_all_locations.php`, `get_sorted_filters.php` | filters |
| `update_employee_status.php`, `check_location_by_ip.php` | WiFi auto check-in by IP range |
| `update_sub_status_until.php`, `get_button_config_until.php` | sub-status with end date |
| `process_scan.php` | badge scans |
| `generate_badges.php`, `generate_badge_png.php` | badge PDF/PNG |
| `get_user_presentation.php` | presentation mode settings |
| `get_todays_visitors.php`, `get_visitors_inside.php`, `visitor_checkin.php`, `visitor_checkout.php`, `send_visitor_notification.php` | visitors |

Root endpoints: `email-proxy.php` (BHV overview e-mail), `cron_endpoint.php` (external cron).

## Background jobs (`cron/`)

| Script | Job |
|---|---|
| `daily_reset_cron.php` | daily status reset |
| `cron_auto_checkout.php` | automatic checkout |
| `cron_reset_expired_sub_status.php` | clear expired sub-statuses |
| `cron_auto_delete.php` | delete old data |
| `cleanup_tokens_cron.php` | remove expired tokens |

Run from the hosting panel (CLI) or via `cron_endpoint.php?action=…&key=…` from cron-job.org.

## Database

31 tables, defined in `install.sql` (the single schema file). Core ones:

| Table | Holds |
|---|---|
| `config` | one row (`id = 1`) with site settings, licence data and continuity flags |
| `users`, `user_groups`, `user_sessions`, `remember_tokens` | accounts, groups, sessions |
| `employees` | employees; `locatie`/`afdeling` are plain text; `home_locatie` = permanent location |
| `locations`, `afdelingen` | locations and departments |
| `visitors` | visitor registrations |
| `kiosk_tokens` | kiosk sign-in tokens |
| `employee_audit`, `feature_audit` | audit logs (migrations log here too) |
| `license_tiers`, `license_log` | licence tiers and licence events |
| `button_config`, `user_button_names`, `filters`, `user_filters`, `user_locations`, `user_afdelingen` | screen configuration per user |

Schema changes: `includes/migrations.php` upgrades existing installs (idempotent,
runs on admin page load) and `install.sql` gets the same change for new installs —
`npm test` and `tests/db_smoke.php` check they agree.

## Licensing

`includes/license.php`: six tiers (Starter free; Professional, Business, Enterprise,
Corporate, Unlimited), limits per tier (`canAddUser/Employee/Location/Department`),
features per tier (`hasFeature`), domain-locked activation against peopledisplay.nl and
continuity keys (Unlimited/Corporate) that permanently lift the domain lock.

## Updates and releases

- `includes/update_check.php` reads `https://admin.peopledisplay.nl/updates/version.json`
  (cached 12 h) and shows the banner on the admin dashboard; `admin/update_install.php`
  installs a delta or full ZIP and keeps customer config files.
- `build_update.php` (on admin.peopledisplay.nl, not in this repo) zips the golden master
  in `admin.peopledisplay.nl/install/`, writes `version.json`, `includes/version.php` and
  `CHANGELOG.md`, and builds the delta ZIP from `update_files.txt`.
- From this repo: `npm run deploy -- --target=golden` fills the golden master and
  `update_files.txt`. See `CLAUDE.md` → Release flow.
- New customers download from GitHub: pushing a tag `vX.Y.Z` runs `.github/workflows/release.yml`,
  which builds `peopledisplay-starter.zip` (same content as build_update.php's installation ZIP)
  and publishes it as a GitHub release; `website/download.php` on peopledisplay.nl points to the
  latest release.

## Developer tooling (never on a server)

| Path | Purpose |
|---|---|
| `package.json` | npm scripts: `test`, `lint`, `changelog`, `sync-sql`, `deploy` |
| `scripts/lib/files.mjs` | which files go to which server (site / golden profile) |
| `scripts/deploy.mjs` | FTP upload of changed files |
| `scripts/changelog.mjs` | CHANGELOG.md from version.json |
| `scripts/build-release.mjs` | installation ZIP + release notes into `dist/` |
| `.github/workflows/release.yml` | GitHub release on every version tag |
| `website/download.php` | download page on peopledisplay.nl (not part of the app) |
| `docs/tools/` | manual screenshots (demo seed + Playwright) and PDF/Word build — see `docs/README.md` |
| `scripts/sync-sql.mjs`, `scripts/lint.mjs` | schema copy, PHP syntax check |
| `tests/foundation.test.mjs` | project checks (`npm test`) |
| `tests/db_smoke.php` | optional database check against a throw-away MariaDB |
| `tools/` | user-manual generator (docx) |
