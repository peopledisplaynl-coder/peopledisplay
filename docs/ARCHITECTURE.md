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
  SuperAdmin can restrict what an admin may do per feature.
- Per-user screen features are flags in the JSON column `users.features`
  (e.g. `inuitBord`, `sorteerFunctie`, `sorteerStandaard`, `zoomFunctie`, `zoomStandaard`),
  managed in `admin/users_manage.php` / `admin/features_manage.php`.

## Main screens

| File | Purpose |
|---|---|
| `login.php`, `logout.php` | sign-in (with remember-me tokens) |
| `kiosk_login.php` | automatic sign-in for kiosk screens via a kiosk token |
| `frontpage.php` | start screen with navigation buttons |
| `index.php` + `app.js`, `style.css` | the card check-in screen (IN/OUT, sub-status, location pin, filters, sort, zoom, BHV) |
| `inuitbord.php` | IN/UIT board display mode (two columns, auto-refresh) |
| `infoscherm.php` | Infoscreen: own messages, news slides, weather, clock and a headline ticker; `?embed=1` inside the idle overlay of `index.php`, combined mode shows Google Slides in the main area |
| `infoscherm_berichten.php` | infoscreen messages for editors (users with the feature `infoscreenEditor`): only for their own locations; admins with *Infoscherm beheren* may use all |
| `overzicht.php` + `overzicht.js` | presence overview |
| `scan.php` | badge/QR scan check-in |
| `visitor_register.php`, `visitor_checkin.php`, `visitor_checkout.php` | visitor registration |
| `bhv-print/` | printable BHV (emergency response) overview |
| `user/profile.php` | own profile, photo, password |
| `admin/dashboard.php` | admin home: tiles, update banner, migrations |
| `admin/*_manage.php` | employees, users, locations, departments, visitors, kiosk tokens, badges, features, config, infoscreen (`infoscreen_manage.php`: messages, news feeds, weather, display settings, screen mode per user) |
| `admin/cron_manage.php` | "Automatische taken": cron commands, web links with the installation key, new key, switch off the old shared key |
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
| `users_create.php`, `users_update.php`, `users_delete.php`, `user_delete.php`, `users_password.php` | user management |
| `force_logout_user.php`, `get_online_users.php` | online users and forced logout |
| `features.php`, `features_update.php`, `user_features.php`, `get-user-features.php` | per-user feature flags |
| `afdelingen_api.php` | departments |
| `get_locations_ordered.php`, `save_locations_order.php`, `sync_locations_smart.php` | locations and their order |
| `save_user_button_names.php`, `check_button_names_api.php` | button labels / status buttons |
| `kiosk_token_actions.php` | kiosk tokens |
| `inuitbord-api.php` | data for the IN/UIT board |
| `get-config.php` | configuration for screens |
| `change_password.php`, `update_profile.php`, `upload_profile_photo.php`, `delete_profile_photo.php` | own profile |
| `changelog.php`, `dismiss_update.php` | update banner and changelog |
| `proxy.php` | server-side proxy for external requests |
| `infoscreen_web_preview.php` | infoscreen website source: fetch a page once and return the feeds it links to and the news lists it recognises (admins with `manage_infoscreen`, CSRF) |

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
| `get_user_presentation.php` | presentation mode settings (Slides ID, idle seconds, `screen_mode`) |
| `infoscreen_data.php` | everything the infoscreen shows, filtered for the screen account's locations; fetches due sources after the response. `?as=<user id>` (2.8.0): preview of another screen account, only with the admin feature `manage_infoscreen`; `?loc=<name>` (2.9.0): preview of one location, for managers and for users with an infoscreen right for that location |
| `infoscreen_push.php` | Berichten-API: other systems post, update, list or delete messages with a connection key (Bearer / X-Api-Key), no session |
| `infoscreen_teams.php` | Teams outgoing webhook (`?k=<id>`): HMAC-signed channel messages become infoscreen messages; replies in the channel |
| `get_todays_visitors.php`, `get_visitors_inside.php`, `visitor_checkin.php`, `visitor_checkout.php` | visitors |

Root endpoints: `email-proxy.php` (BHV overview e-mail), `cron_endpoint.php` (external cron).

## Background jobs (`cron/`)

| Script | Job |
|---|---|
| `daily_reset_cron.php` | daily status reset |
| `cron_auto_checkout.php` | automatic checkout |
| `cron_reset_expired_sub_status.php` | clear expired sub-statuses |
| `cron_auto_delete.php` | delete old data |
| `cleanup_tokens_cron.php` | remove expired tokens |
| `cron_infoscreen.php` | fetch infoscreen news feeds and weather (every 15 min; also `cron_endpoint.php?action=infoscreen_refresh`) |

Run from the hosting panel (CLI) or via `cron_endpoint.php?action=…&key=…` / `cron/<script>.php?key=…` from cron-job.org.
Since 2.6.0 the key is per installation (`includes/cron_key.php`, stored in `data/cron_key.php`, shown
in `admin/cron_manage.php`). Every web entry calls `pd_cron_require_key()`. The old shared key from
2.5 and earlier keeps working for 30 days on installations that existed before the update; each such
call is recorded in `data/cron_legacy_used.json` and the dashboard warns. CLI calls need no key.

## Database

41 tables, defined in `install.sql` (the single schema file). Core ones:

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
| `infoscreen_messages`, `infoscreen_sources`, `infoscreen_items`, `infoscreen_events`, `infoscreen_settings`, `infoscreen_api_keys` | infoscreen: messages, news feeds, weather places and iCal agendas (with cached items and expanded appointments), settings, connections (API keys, Teams webhooks); `users.screen_mode` picks Slides / infoscreen / combined |

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

## Infoscreen (2.4.0)

`includes/infoscreen.php` holds everything: the schema (`pd_infoscreen_ensure_schema()`, also
called by the infoscreen pages so a screen works before an admin visits the dashboard), the
fetchers and parsers, and `pd_infoscreen_screen_data()` for the screen.

- **Sources.** RSS 2.0, RSS 1.0/RDF and Atom (images from enclosures, Media RSS, or the first
  `<img>` in the text); weather from Buienradar (nearest station with a temperature, 5-day
  forecast, weather report) or Open-Meteo. Both weather sources are free for non-commercial use
  with attribution; the screen always shows the source. News images are hot-linked, never copied.
- **Fetching.** At most every 15 minutes per source, one run at a time (MySQL `GET_LOCK`), from
  `cron/cron_infoscreen.php`, `cron_endpoint.php?action=infoscreen_refresh`, or after a screen
  request (`fastcgi_finish_request()`, otherwise briefly before the answer). Outgoing requests
  only go to public addresses (no loopback/private/link-local, also after redirects), with a
  timeout and a 3 MB cap.
- **Screen.** `infoscherm.php` polls `api/infoscreen_data.php` every 5 minutes, keeps the last
  answer in `localStorage` for outages, reloads itself after a version change or after 24 hours,
  and reports touches to the parent page (`postMessage`) when shown in the idle overlay.
- **BHV and birthdays (2.8.0).** `pd_infoscreen_bhv_present()` counts `employees` with `bhv = 'Ja'`,
  `status = 'IN'` and no `sub_status` (the extra buttons also set IN) at the screen's locations
  (`locatie`); with the block on, the screen polls every 2 minutes. `employees.verjaardag` is
  `CHAR(5)` "MM-DD" (no year; filling it in is the opt-in), helpers in `includes/birthday.php`;
  `pd_infoscreen_birthdays()` matches today in Europe/Amsterdam (29-02 on 28-02 in other years)
  against the home location (`COALESCE(home_locatie, locatie)`). Settings `bhv_card`, `bhv_names`,
  `birthday_slide` (all in `infoscreen_settings`), off by default.
  Per screen (2.8.1): `users.features.infoscreenParts` = `{"birthdays": "on"|"off", "bhv": "on"|"off"}`
  (missing = the global setting), set on the Schermen tab; `users_manage.php` keeps the key when it
  rebuilds `features`.
- **Huisstijl (2.9.0).** `includes/infoscreen_branding.php`, table `infoscreen_branding` (one row per
  location, `location_name` '' = organisation; created by `pd_infoscreen_ensure_schema()`, which also
  moves the old `infoscreen_settings.title` into the organisation row). Empty fields of a location
  follow the organisation; colours go as one group (`use_colors`). A screen with one location uses
  that location, with more locations the organisation, unless `users.features.infoscreenBrand` says
  otherwise ('' = organisation, or a location; set on the Schermen tab). `pd_branding_resolve()` sends
  colours, the derived text colours (WCAG contrast, white or #10213a) and a system font stack;
  nothing is loaded from the internet. Logos: PNG/JPG/GIF/WEBP only (no SVG: it could carry script).
  Message colours `huisstijl` and `accent` follow the screen's huisstijl.
- **Infoscreen rights (2.9.0).** `users.features.infoscreenEditor` (messages) and `infoscreenBranding`
  (huisstijl) for `features.infoscreenLocations` (falls back to `features.locations` for users from
  before 2.9.0); `pd_infoscreen_editor_scope()`; page `infoscherm_berichten.php` with tabs.
  `users_manage.php` keeps `infoscreenParts`, `infoscreenBrand` and `infoscreenLocations` when it
  rebuilds `features`.
- **Image placement (2.9.0).** `infoscreen_messages.image_layout` (left, right, top, full),
  `image_fit` (cover, contain) and `image_size` (30-70 %); a message without text always shows its
  image across the whole slide.
- **Preview per screen (2.8.0).** `infoscherm.php?preview=1&as=<id>` loads the data of that account
  (checked again in the API) and keeps its own `localStorage` cache key, so a preview never
  overwrites the cache of a real screen in the same browser.
- **Agenda (2.5.0).** `includes/infoscreen_ical.php` reads an .ics (Outlook published calendar, Google
  secret address), expands repeating appointments (RRULE subset, EXDATE, RECURRENCE-ID, Windows
  time zone names) and stores 60 days ahead in `infoscreen_events`; private appointments are skipped.
- **Connections (2.5.0).** `includes/infoscreen_api.php`: Berichten-API keys (only the SHA-256 is
  stored, shown once) and Teams outgoing webhooks (security token encrypted with the 2FA key file,
  HMAC-SHA256 checked in constant time). 120 requests per hour per connection; a connection only
  changes its own messages. `api/.htaccess` passes the Authorization header on FastCGI hosting.
- **Locations (2.6.0).** Messages, sources and connections have `locations` (JSON list of
  `locations.location_name`, NULL = all locations); the old single `location` column is kept but no
  longer used (backfilled on migration). `pd_infoscreen_locs_match()` filters in PHP for the screen
  account's `features.locations`. Renaming a location (`admin/locations_manage.php`) calls
  `pd_infoscreen_rename_location()`.
- **Editors (2.6.0).** `pd_infoscreen_editor_scope()` gives `all` for SuperAdmin and admins with
  `manage_infoscreen`, otherwise the user's `features.locations` when `features.infoscreenEditor` is
  set. An editor may only create, change, switch off or delete messages whose locations are all
  within that scope (`pd_infoscreen_scope_allows()`); messages for all locations are read-only.
- **Website sources (2.7.0).** `includes/infoscreen_web.php`: a news source with `provider = 'web'`
  (type stays `rss`) and its settings in `data_json` (`mode` auto / feed / list / css). Feed discovery
  (`<link rel=alternate>`, WordPress `/feed/`), list recognition (repeated siblings around links, `<article>`,
  menus/headers/footers/social rows skipped, scored on count, title length, images, dates, text), the chosen
  list stored as an XPath, and simple CSS selectors converted to XPath. Same SSRF-guarded HTTP fetch and item
  storage (`pd_infoscreen_store_items()`) as feeds. No JavaScript is run: script-built pages give no items.
  The demo/screenshot server loads `docs/tools/http-fixtures.php` so `*.voorbeeld.test` pages come from fixtures.
- **Tests.** `tests/infoscreen_test.php` (fixtures in `tests/fixtures/infoscreen/`), run by `npm test`.
