# PeopleDisplay — Development Rules

PeopleDisplay is a PHP/MySQL presence and check-in system (PWA + kiosk screens) for
schools and childcare organisations: employees check IN/OUT on a card screen, admins
manage employees, locations, departments, visitors, BHV, badges and licences.
Owner and sole developer: Ton Labee. Open Core: Starter is AGPL, paid tiers need a
licence key (see `includes/license.php`).

Talk to Ton in Dutch. Code, comments and technical docs in English; user-facing
texts in Dutch.

Read `docs/ARCHITECTURE.md` before larger changes.

## Hosting constraints (these are design rules)

- Ordinary shared hosting (Strato now, Cloud86 planned): Plesk/FTP only, no SSH,
  no Node or Composer on the server, no long-running processes.
- Background work runs from cron scripts in `cron/` (hosting panel cron or
  cron-job.org via `cron_endpoint.php`) — never daemons or workers.
- PHP 8.0+ (servers run up to 8.4), MySQL 5.7+/MariaDB 10.3+.
- No XAMPP / local PHP server in Ton's workflow: testing happens on
  `test.peopledisplay.nl` after an FTP upload.

## Environments — never mix them up

| Where | What |
|---|---|
| `D:\PeopleDipsplay_CLEAN_INSTALL\peopledisplay_install\` | this repository (GitHub `peopledisplaynl-coder/peopledisplay`, branch `main`) |
| `test.peopledisplay.nl` | test install — `npm run deploy` |
| `mijn.peopledisplay.nl` | live customer install — `npm run deploy -- --target=live` |
| `admin.peopledisplay.nl/install/` | golden master that `build_update.php` zips — `npm run deploy -- --target=golden` |
| `admin.peopledisplay.nl/updates/version.json` | the **real** published version + changelog customers see |

`build_update.php` lives on admin.peopledisplay.nl, not in this repo. The local
`D:\…\admin.peopledisplay.nl\` mirror is often stale — trust the live server.

## Commands (run from the repository root, Node 20+)

```powershell
npm install                      # once: installs the FTP client for the deploy script
npm test                         # all project checks (PHP syntax check too, when PHP is installed)
npm run lint                     # PHP syntax check only (needs php on PATH)
npm run changelog                # regenerate CHANGELOG.md from version.json
npm run sync-sql                 # copy install.sql → install/sql/install.sql
npm run deploy                   # FTP changed files to test.peopledisplay.nl
npm run deploy -- --target=golden  # FTP changed files to admin.peopledisplay.nl/install/
npm run deploy -- --target=live    # FTP to mijn.peopledisplay.nl (asks to type "live")
npm run deploy -- --dry-run      # show what would be uploaded
npm run release:build            # build dist/peopledisplay-starter.zip (same as the installation ZIP)
```

Deploy details: credentials in `deploy.config.json` (git-ignored, copy
`deploy.config.example.json`). `"protocol": "sftp"` (Strato's `*.ssh.*.strato.hosting`,
port 22) or `"ftp"` (port 21, `"secure": true` for FTPS). Upload state per target in `.deploy-state/`; only
changed files go up, nothing is ever deleted on the server. First use on a server
that already matches a commit: `npm run deploy -- --baseline=HEAD`. What may be
uploaded where is defined once in `scripts/lib/files.mjs` (site installs never get
installers, `.htaccess`, `includes/config.php`, db_config, uploads/logs/tmp/backups
or any developer file). For the golden target the script also writes and uploads
`update_files.txt` (files changed since the latest git tag) for the delta ZIP.

Optional database check (needs PHP + a throw-away MariaDB, never a real DB):
`PD_TEST_DSN="mysql:host=127.0.0.1;port=3307" PD_TEST_USER=… PD_TEST_PASS=… php tests/db_smoke.php`

## Version rule

- `version.json` and `includes/version.php` always carry the same version, changed
  in the same commit. Both constants are required (missing one causes reload loops):
  ```php
  define('PD_CURRENT_VERSION', 'x.y.z');
  define('PEOPLEDISPLAY_VERSION', 'x.y.z'); // alias voor backward compatibility
  ```
  Keep `includes/version.php` in exactly the format `build_update.php` writes.
- The installer reads `PD_CURRENT_VERSION`; never hard-code a version anywhere else.
- `CHANGELOG.md` is generated from `version.json` (`npm run changelog`), same format
  as `build_update.php` — never edit it by hand.
- Semver: patch for fixes/maintenance, minor for features.

## Conventions

- **Database schema change** → always both:
  1. `includes/migrations.php` (idempotent; runs on admin page load for admin/superadmin;
     new `employees` columns go in `$empCols`, new `config` columns in `$configCols`), and
  2. `install.sql` in the repository root (the one schema file), then `npm run sync-sql`.
  Never a standalone SQL file Ton has to run by hand. `npm test` fails when a migrated
  table, column or ENUM is missing from `install.sql`.
- **New admin endpoint** → `admin/api/<name>.php` (admin APIs always under `/admin/api/`;
  `/api/` holds the existing screen/kiosk/visitor endpoints) + an entry in the API table
  of `docs/ARCHITECTURE.md`.
- **New per-user feature flag** → a key in the `users.features` JSON column (not a new
  table), checkbox in `admin/users_manage.php`, read in the page/JS that uses it.
- **New admin page** → include `admin/auth_helper.php` after `includes/db.php`, and
  `includes/license_check.php` / `requireFeature()` when tier-gated.
- Database variable is always `$db` (PDO) in new code, never `$pdo`.
- File headers: only the filename and upload path (e.g. `/includes/version.php`) —
  no domains or server directory layout.
- Feature flags and settings are read server-side; never trust client state for
  status changes (e.g. checkout uses `COALESCE(home_locatie, locatie)` server-side).

## Gotchas

- `session_config.php` does not exist on the server — never `require` it.
- The `config` table's value column name differs on some servers — check before querying.
- `employees.locatie` / `afdeling` are plain text, not foreign keys: renaming a location
  or department needs explicit `UPDATE employees` queries.
- `fputcsv()` on PHP 8.4 needs the explicit `$escape` argument.
- Edit `app.js` byte-safely (it contains emoji and uses CRLF line endings); in Python
  use binary mode.
- Service worker: PHP, `app.js` and `style.css` are never cached; after JS changes
  bust the cache (`?v=`) and unregister the service worker in DevTools when testing.
- iOS Safari has no automatic PWA install; iPadOS reports as "Macintosh"
  (detect via touch points > 1).
- The update banner caches `version.json` for 12 h in the system temp dir; installs on
  one hosting account share it.
- `includes/config.php` is tracked code (loads the `config` row) even though
  `.gitignore` lists it; `build_update.php` keeps it out of update ZIPs.
- Ton uses the local Claude Code CLI. Commits with "Co-authored-by: Copilot" and
  `agents/…` branches come from GitHub's cloud agent and must be merged into `main`
  locally (`git fetch origin` + `git merge origin/agents/<branch>`).

## Release flow (per version)

1. Build the change completely (backend, frontend, migration + install.sql, texts, docs).
2. `npm test`; then `npm run deploy` and test on test.peopledisplay.nl.
3. Bump the version in `version.json` + `includes/version.php`, add the changelog entry,
   `npm run changelog`.
4. `npm run deploy -- --target=golden`, then in `build_update.php`: publish the version with
   the same changelog lines, build the delta ZIP (update_files.txt is already filled),
   refresh the installation ZIP when install files changed.
   Click "Nieuwe versie publiceren" ONCE: afterwards the form pre-fills the next number, and a
   second click publishes an empty version.
5. Commit and push FIRST, then tag that commit: `git tag vX.Y.Z` + `git push origin vX.Y.Z`.
   The tag is what the next update_files.txt is computed from, and pushing it starts
   `.github/workflows/release.yml`: checks, then `scripts/build-release.mjs` builds
   `peopledisplay-starter.zip` and publishes it as the GitHub release (refused when the tag
   does not match `version.json`). A moved tag (`git tag -f` + `git push --force`) rebuilds it.

## Downloads for new customers

`website/download.php` is the page on peopledisplay.nl (not part of the app; upload it to the
website by hand when it changes). It reads the newest GitHub release (cached 1 h) and sends the
visitor to its `peopledisplay-starter.zip`; stable link:
`https://github.com/peopledisplaynl-coder/peopledisplay/releases/latest/download/peopledisplay-starter.zip`.
Existing installations keep updating through admin.peopledisplay.nl (`build_update.php`).
