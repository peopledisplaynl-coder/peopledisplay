// tests/foundation.test.mjs
// Project checks that catch the mistakes that cost the most time before:
// version numbers out of step, a schema change in migrations.php that never made it
// into install.sql, two installers with different schemas, secrets in the repository,
// and the deploy script picking up files that must stay off a server.
//
// Run: npm test   (Node only; the PHP syntax check runs when PHP is installed)

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { ROOT, filesForProfile, isExcluded, PROTECTED, DEV_ONLY } from '../scripts/lib/files.mjs';
import { expectedChangelog } from '../scripts/changelog.mjs';

const read = (p) => readFileSync(join(ROOT, p), 'utf8').replace(/\r\n/g, '\n');

// ── Versions ───────────────────────────────────────────────────────────
test('version.json and includes/version.php carry the same version', () => {
  const json = JSON.parse(read('version.json'));
  const php = read('includes/version.php');
  const constant = (name) => php.match(new RegExp(`define\\('${name}',\\s*'([^']+)'\\)`))?.[1];
  assert.match(json.version, /^\d+\.\d+\.\d+$/);
  assert.equal(constant('PD_CURRENT_VERSION'), json.version, 'PD_CURRENT_VERSION ≠ version.json');
  assert.equal(constant('PEOPLEDISPLAY_VERSION'), json.version, 'PEOPLEDISPLAY_VERSION ≠ version.json');
});

test('version.json changelog starts with the current version and has no duplicates', () => {
  const json = JSON.parse(read('version.json'));
  assert.equal(json.changelog[0].version, json.version);
  const versions = json.changelog.map((e) => e.version);
  assert.equal(new Set(versions).size, versions.length, 'duplicate version in changelog');
  for (const e of json.changelog) {
    assert.match(e.date, /^\d{4}-\d{2}-\d{2}$/, `bad date for ${e.version}`);
    assert.ok(e.changes.length > 0, `no changes listed for ${e.version}`);
  }
});

test('CHANGELOG.md is generated from version.json (npm run changelog)', () => {
  assert.equal(read('CHANGELOG.md'), expectedChangelog());
});

test('installer shows the version from includes/version.php, not a hard-coded number', () => {
  for (const f of ['install/index.php', ...[1, 2, 3, 4, 5, 6].map((n) => `install/steps/step${n}.php`)]) {
    if (!existsSync(join(ROOT, f))) continue;
    assert.doesNotMatch(read(f), /PeopleDisplay v\d+\.\d+\.\d+|INSTALLER_VERSION',\s*'\d/, `${f} contains a hard-coded version`);
  }
});

// ── Database schema ────────────────────────────────────────────────────
function tablesIn(sql) {
  const tables = {};
  for (const m of sql.matchAll(/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\(([\s\S]*?)\)\s*ENGINE/gi)) {
    tables[m[1]] = m[2];
  }
  return tables;
}

test('install wizard uses an exact copy of install.sql (npm run sync-sql)', () => {
  assert.equal(read('install/sql/install.sql'), read('install.sql'));
});

test('install.sql table count comment matches its CREATE TABLE statements', () => {
  const sql = read('install.sql');
  const count = Object.keys(tablesIn(sql)).length;
  assert.equal(Number(sql.match(/-- Tables created: (\d+)/)?.[1]), count);
  assert.equal(Number(sql.match(/-- Tables: (\d+)/)?.[1]), count);
});

test('every table and column that migrations.php adds also exists in install.sql', () => {
  // includes/security.php maakt zijn eigen tabel/kolommen aan (de migraties draaien alleen voor beheerders)
  const migrations = read('includes/migrations.php') + '\n' + read('includes/security.php');
  const tables = tablesIn(read('install.sql'));

  for (const m of migrations.matchAll(/CREATE TABLE IF NOT EXISTS `(\w+)`/g)) {
    assert.ok(tables[m[1]], `table ${m[1]} is created in migrations.php but missing from install.sql`);
  }

  // Column arrays: $empCols → employees, $configCols → config
  const arrays = { empCols: 'employees', configCols: 'config' };
  for (const [variable, table] of Object.entries(arrays)) {
    const block = migrations.match(new RegExp(`\\$${variable}\\s*=\\s*\\[([\\s\\S]*?)\\];`))?.[1];
    assert.ok(block, `$${variable} not found in migrations.php`);
    for (const col of block.matchAll(/'(\w+)'\s*=>/g)) {
      assert.match(tables[table], new RegExp('`' + col[1] + '`'), `${table}.${col[1]} is added in migrations.php but missing from install.sql`);
    }
  }

  // Direct ALTER TABLE ... ADD COLUMN `x`
  for (const m of migrations.matchAll(/ALTER TABLE `(\w+)` ADD COLUMN `(\w+)`/g)) {
    assert.match(tables[m[1]], new RegExp('`' + m[2] + '`'), `${m[1]}.${m[2]} missing from install.sql`);
  }

  // ENUMs that migrations widen must already be complete in install.sql
  for (const m of migrations.matchAll(/ALTER TABLE `(\w+)` MODIFY `(\w+)` ENUM\(([^)]*)\)/g)) {
    const def = tables[m[1]].match(new RegExp('`' + m[2] + '`\\s+enum\\(([^)]*)\\)', 'i'))?.[1];
    assert.ok(def, `${m[1]}.${m[2]} enum not found in install.sql`);
    const norm = (s) => s.replace(/\s/g, '').toLowerCase();
    assert.equal(norm(def), norm(m[3]), `${m[1]}.${m[2]} enum differs between migrations.php and install.sql`);
  }
});

// ── Secrets ────────────────────────────────────────────────────────────
test('no secrets or real config files in anything that can be uploaded', () => {
  const patterns = [
    [/ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}/, 'GitHub token'],
    [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, 'private key'],
    [/sk_live_[A-Za-z0-9]{10,}/, 'live API key'],
  ];
  for (const f of filesForProfile('golden')) {
    if (f.startsWith('tcpdf/') || /\.(png|jpe?g|gif|ico|webp|docx|pdf|zip|ttf|woff2?|z)$/i.test(f)) continue;
    const text = readFileSync(join(ROOT, f), 'latin1');
    for (const [re, what] of patterns) assert.doesNotMatch(text, re, `${what} found in ${f}`);
  }
  for (const f of ['includes/config.json', 'includes/db_config.php', 'admin/db_config.php', 'deploy.config.json']) {
    const tracked = spawnSync('git', ['ls-files', '--error-unmatch', f], { cwd: ROOT }).status === 0;
    assert.ok(!tracked, `${f} must not be in git`);
  }
});

// ── Deploy selection ───────────────────────────────────────────────────
test('deploy never uploads server config, customer data or developer files', () => {
  for (const profile of ['site', 'golden']) {
    const files = filesForProfile(profile);
    for (const f of files) {
      assert.ok(!isExcluded(f, PROTECTED), `${profile}: protected file ${f} selected`);
      assert.ok(!isExcluded(f, DEV_ONLY), `${profile}: developer file ${f} selected`);
    }
  }
  const site = filesForProfile('site');
  for (const f of ['install.php', 'install.sql', 'includes/config.php', '.htaccess']) {
    assert.ok(!site.includes(f), `site deploy must skip ${f}`);
  }
  assert.ok(!site.some((f) => f.startsWith('install/')), 'site deploy must skip the install wizard');
  assert.ok(site.includes('index.php') && site.includes('includes/version.php'));

  const golden = filesForProfile('golden');
  assert.ok(golden.includes('install.sql') && golden.some((f) => f.startsWith('install/')), 'golden master needs the installers');
});

test('installation ZIP for new customers contains the installers and no config or developer files', () => {
  const release = filesForProfile('release');
  for (const f of ['install.php', 'install.sql', 'install/index.php', 'index.php', 'includes/version.php', 'LICENSE']) {
    assert.ok(release.includes(f), `release ZIP must contain ${f}`);
  }
  for (const f of release) {
    assert.ok(!isExcluded(f, PROTECTED) && !isExcluded(f, DEV_ONLY), `release ZIP must not contain ${f}`);
  }
  for (const f of ['includes/config.php', 'config.example.php', 'website/download.php', '.github/workflows/release.yml']) {
    assert.ok(!release.includes(f), `release ZIP must not contain ${f}`);
  }
});

test('release build refuses a tag that does not match version.json', () => {
  const version = JSON.parse(read('version.json')).version;
  const wrong = spawnSync(process.execPath, [join(ROOT, 'scripts', 'build-release.mjs'), '--tag=v0.0.0'], { encoding: 'utf8' });
  assert.notEqual(wrong.status, 0, 'a wrong tag must fail');
  assert.match(wrong.stderr, new RegExp(version.replace(/\./g, '\\.')));
});

// ── Security ───────────────────────────────────────────────────────────
test('no temporary helper scripts, debug scripts or files from the obsolete list ship in the product', () => {
  const files = filesForProfile('golden');
  const bad = files.filter((f) => /(^|\/)(reset_password|[^/]*_debug)\.php$/.test(f));
  assert.deepEqual(bad, [], `temporary or debug scripts in the product: ${bad.join(', ')}`);

  const list = read('includes/obsolete_files.php');
  const obsolete = [...list.matchAll(/^\s*'([^']+\.(?:php|js))',\s*(?:\/\/.*)?$/gm)].map((m) => m[1]);
  assert.ok(obsolete.length > 5, 'could not read the obsolete list');
  const stillThere = obsolete.filter((f) => existsSync(join(ROOT, f)));
  assert.deepEqual(stillThere, [], `files on the obsolete list still exist: ${stillThere.join(', ')}`);
});

test('every API script checks the login, except the few that are public on purpose', () => {
  const PUBLIC_ON_PURPOSE = [
    'api/app-version.php',               // only the version number
    'api/check_location_by_ip.php',      // location name for the WiFi check-in
    'api/get_employees_by_location.php', // visitor form: names only, no e-mail addresses
    'admin/api/changelog.php',           // public version history
  ];
  const isApi = (f) => /^(api|admin\/api)\/[^/]+\.php$/.test(f);
  const hasCheck = (text) => /requireAuth\(|requireAdmin\(|\$_SESSION\[\s*['"](user_id|role)['"]\s*\]/.test(text);
  const missing = filesForProfile('golden')
    .filter(isApi)
    .filter((f) => !PUBLIC_ON_PURPOSE.includes(f))
    .filter((f) => !hasCheck(read(f)));
  assert.deepEqual(missing, [], `API without login check (add requireAuth(), or list it as public on purpose): ${missing.join(', ')}`);
});

test('email-proxy.php requires a login, POST and the same site', () => {
  const t = read('email-proxy.php');
  assert.match(t, /\$_SESSION\['user_id'\]/, 'email-proxy.php must require a login');
  assert.match(t, /REQUEST_METHOD/, 'email-proxy.php must only accept POST');
  assert.match(t, /HTTP_ORIGIN/, 'email-proxy.php must check the Origin header');
});

test('changing an employee status needs POST from the same site, and app.js no longer changes it through the URL', () => {
  for (const f of ['admin/api/employees_api.php', 'api/update_employee_status.php']) {
    assert.match(read(f), /pd_require_post_same_origin\(\)/, `${f} must call pd_require_post_same_origin()`);
  }
  assert.doesNotMatch(read('app.js'), /action=updatestatus&id=/, 'app.js still changes the status with a GET URL');
  assert.match(read('app.js'), /method:\s*'POST'/, 'app.js should POST the status change');
});

test('get_employees_by_location.php (public) does not return e-mail addresses', () => {
  assert.doesNotMatch(read('api/get_employees_by_location.php'), /^\s*email\b/m);
});

test('BHV print page has no preset example e-mail recipient', () => {
  assert.doesNotMatch(read('bhv-print/bhv-print.html'), /@email\.com/i);
});

test('location list is loaded even when the building menu is absent (own-card mode)', () => {
  const app = read('app.js');
  const start = app.indexOf('async function renderBuildings');
  const body = app.slice(start, start + 6000);
  const setList = body.indexOf('window.__allLocations = [...orderedLocs]');
  const earlyReturn = body.indexOf('if(!menu) return;');
  assert.ok(setList > -1 && earlyReturn > -1, 'renderBuildings structure changed');
  assert.ok(setList < earlyReturn, 'window.__allLocations must be set before the menu check');
});

test('login checks the block list before the password and records every attempt', () => {
  const login = read('login.php');
  assert.match(login, /pd_login_wait_seconds\(/, 'login.php must check for a block before verifying the password');
  assert.match(login, /pd_login_record\(/, 'login.php must record login attempts');
  assert.ok(login.indexOf('pd_login_wait_seconds(') < login.indexOf('password_verify('), 'block check must come before password_verify');
  assert.match(login, /pd_login_start_session\(/, 'login.php must start the session through pd_login_start_session (new session id)');
  assert.doesNotMatch(login, /Onverwachte fout: ' \. \$e->getMessage/, 'login.php must not show internal error messages');
});

test('user management does not write a debug log with the submitted form (passwords)', () => {
  const um = read('admin/users_manage.php');
  assert.doesNotMatch(um, /users_manage_debug/);
  assert.doesNotMatch(um, /json_encode\(\$_POST\)/);
  assert.match(read('includes/obsolete_files.php'), /'users_manage_debug\.log'/, 'old debug log must be removed by the cleanup list');
});

test('every place that sets a password in the app uses the password policy', () => {
  for (const f of ['admin/users_manage.php', 'admin/change_password.php', 'admin/api/change_password.php',
                   'admin/api/users_create.php', 'user/profile.php', 'force_password_change.php']) {
    assert.match(read(f), /pd_password_policy_error\(/, `${f} must check the password policy`);
  }
});

test('forced password change is enforced centrally from includes/db.php', () => {
  assert.match(read('includes/db.php'), /pd_enforce_password_change\(/);
  assert.match(read('includes/security.php'), /function pd_enforce_password_change/);
  assert.ok(existsSync(join(ROOT, 'force_password_change.php')), 'force_password_change.php must exist');
  assert.match(read('kiosk_login.php'), /\$_SESSION\['kiosk_login'\] = true/, 'kiosk sessions must be marked so they are exempt');
});

// ── PHP syntax ─────────────────────────────────────────────────────────
const hasPhp = spawnSync('php', ['-v']).status === 0;
test('all PHP files have valid syntax', { skip: hasPhp ? false : 'PHP not installed on this computer' }, () => {
  const r = spawnSync(process.execPath, [join(ROOT, 'scripts', 'lint.mjs')], { encoding: 'utf8' });
  assert.equal(r.status, 0, r.stdout + r.stderr);
});

// ── Manuals ────────────────────────────────────────────────────────────
test('manuals: every screenshot exists, and Dutch and English use the same ones', () => {
  const pairs = [
    ['docs/nl/gebruikershandleiding.md', 'docs/en/user-guide.md'],
    ['docs/nl/beheerdershandleiding.md', 'docs/en/admin-guide.md'],
    ['docs/nl/installatiehandleiding.md', 'docs/en/installation-guide.md'],
  ];
  const images = (f) => [...read(f).matchAll(/!\[[^\]]*\]\(\.\.\/images\/([^)]+)\)/g)].map((m) => m[1]);
  for (const [nl, en] of pairs) {
    for (const img of images(nl)) assert.ok(existsSync(join(ROOT, 'docs', 'images', img)), `${nl}: missing docs/images/${img}`);
    assert.deepEqual(images(en), images(nl), `${en} and ${nl} should show the same screenshots in the same order`);
  }
});
