// tests/foundation.test.mjs
// Project checks that catch the mistakes that cost the most time before:
// version numbers out of step, a schema change in migrations.php that never made it
// into install.sql, two installers with different schemas, secrets in the repository,
// and the deploy script picking up files that must stay off a server.
//
// Run: npm test   (Node only; the PHP syntax check runs when PHP is installed)

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { ROOT, filesForProfile, isExcluded, PROTECTED, DEV_ONLY } from '../scripts/lib/files.mjs';
import { expectedChangelog } from '../scripts/changelog.mjs';

const hasPhp = spawnSync('php', ['-v']).status === 0;

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

test('install.sql has no semicolon in a comment (the install wizard splits on every ;)', () => {
  const bad = read('install.sql').split('\n').filter((l) => /^\s*--.*;/.test(l));
  assert.deepEqual(bad, [], 'remove the semicolon from these comment lines');
});

test('install.sql table count comment matches its CREATE TABLE statements', () => {
  const sql = read('install.sql');
  const count = Object.keys(tablesIn(sql)).length;
  assert.equal(Number(sql.match(/-- Tables created: (\d+)/)?.[1]), count);
  assert.equal(Number(sql.match(/-- Tables: (\d+)/)?.[1]), count);
});

test('every table and column that migrations.php adds also exists in install.sql', () => {
  // includes/security.php, includes/twofa.php en includes/infoscreen.php maken hun eigen tabellen/kolommen aan (de migraties draaien alleen voor beheerders)
  const migrations = read('includes/migrations.php') + '\n' + read('includes/security.php') + '\n' + read('includes/twofa.php') + '\n' + read('includes/infoscreen.php');
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
    'api/infoscreen_push.php',           // infoscreen Berichten-API: own key per connection (checked below)
    'api/infoscreen_teams.php',          // Teams outgoing webhook: HMAC signature (checked below)
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
  assert.match(login, /pd_complete_login\(/, 'login.php must finish the login through pd_complete_login');
  assert.match(read('includes/security.php'), /function pd_complete_login[\s\S]*pd_login_start_session\(/, 'pd_complete_login must start the session through pd_login_start_session (new session id)');
  assert.doesNotMatch(login, /pd_login_start_session\(/, 'login.php must not start the session itself: with 2FA the login is only complete after the code');
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

// ── Two-factor authentication (2.3.0) ──────────────────────────────────
test('2FA: login only becomes a session after the code, and the password step does not reset the failure counter', () => {
  const login = read('login.php');
  assert.match(login, /pd_2fa_challenge_needed\(/);
  assert.match(login, /pd_2fa_set_pending\(/);
  assert.ok(login.indexOf('pd_2fa_challenge_needed(') < login.indexOf('= pd_complete_login('), '2FA check must come before the login is completed');
  assert.match(login, /'pw_ok_2fa'/, 'the password step of a 2FA login must not count as a successful login');
  const sec = read('includes/security.php');
  assert.match(sec, /NOT IN \('blocked', 'pw_ok_2fa'\)/, 'the block counter must ignore the password step of a 2FA login');
  const page = read('login_2fa.php');
  assert.match(page, /pd_login_wait_seconds\(/, 'login_2fa.php must check the block list');
  assert.match(page, /pd_2fa_verify_user_code\(/);
  assert.match(page, /'bad_2fa'/, 'wrong codes must be recorded');
  assert.ok(page.indexOf('pd_login_wait_seconds(') < page.indexOf('pd_2fa_verify_user_code('), 'block check before verifying the code');
  assert.doesNotMatch(page, /\$_SESSION\['user_id'\]\s*=/, 'login_2fa.php must not set the user in the session itself');
});

test('2FA: remember-me never bypasses 2FA, and 2FA setup is enforced centrally from includes/db.php', () => {
  assert.match(read('includes/auth.php'), /pd_2fa_challenge_needed\(/, 'remember-me login must check 2FA');
  assert.match(read('includes/db.php'), /pd_enforce_2fa_setup\(/);
  assert.match(read('includes/twofa.php'), /function pd_enforce_2fa_setup/);
  assert.match(read('includes/twofa.php'), /kiosk_login/, 'kiosk sessions must be exempt');
  assert.match(read('includes/twofa.php'), /FROM kiosk_tokens/, 'kiosk/tablet accounts must never be required to use 2FA');
  assert.match(read('twofa_setup.php'), /empty\(\$_SESSION\['user_id'\]\)/, 'twofa_setup.php needs a login');
  assert.match(read('twofa_setup.php'), /pd_2fa_csrf_ok\(\)/, 'twofa_setup.php must check the CSRF token');
  assert.match(read('force_password_change.php'), /pd_post_login_target\(/, 'after a forced password change the user must get the 2FA invitation (same destination as after login)');
  assert.match(read('includes/twofa.php'), /pd_2fa_invited_at/, 'a reminder for 2FA must be limited to once per 24 hours per session');
});

test('2FA: secrets are encrypted, recovery codes are hashed, and the key file stays on the server', () => {
  const t = read('includes/twofa.php');
  assert.match(t, /aes-256-gcm/);
  assert.match(t, /pd_2fa_encrypt\(\$secretB32\)/, 'the secret must be stored encrypted');
  assert.match(t, /pd_2fa_recovery_hash\(\$raw\)/, 'recovery codes must be stored as a hash');
  assert.ok(PROTECTED.includes('data/'), 'data/ (with twofa_key.php) must be protected from deploy');
  assert.ok(PROTECTED.includes('twofa_uit.txt'));
  assert.match(read('.gitignore'), /^\/data\/$/m, 'data/ must be in .gitignore');
  assert.equal(isExcluded('data/twofa_key.php', [...DEV_ONLY, ...PROTECTED]), true, 'the key file must never ship in a ZIP');
});

test('2FA: the setup page works on a phone (open-in-app link, copy key) and verifies the key that was shown', () => {
  const t = read('twofa_setup.php');
  assert.match(t, /href="<\?= \$h\(\$otpauth\) \?>"/, 'phone users need an otpauth:// link to open their authenticator app');
  assert.match(t, /pdCopyKey/, 'phone users need a button to copy the key');
  assert.match(t, /\(pointer: coarse\)/, 'the phone layout must also apply to touch devices');
  assert.match(t, /pd_2fa_setup_token_read\(/, 'the key that was shown (hidden field) must be what the code is checked against');
  assert.match(t, /no-store/, 'the setup page contains a secret and must not be cached');
});

test('2FA: user management can require it per user and per role, and reset it', () => {
  const um = read('admin/users_manage.php');
  for (const needle of ["'bulk_twofa'", "'reset_2fa'", 'twofa_required', 'pd_2fa_disable(']) {
    assert.ok(um.includes(needle), `users_manage.php must contain ${needle}`);
  }
  assert.match(um, /NOT EXISTS \(SELECT 1 FROM kiosk_tokens/, 'bulk 2FA must skip kiosk accounts');
});

test('2FA: TOTP matches the RFC 6238 test vectors, codes work once, secrets round-trip', { skip: hasPhp ? false : 'PHP not installed on this computer' }, () => {
  const keyDir = mkdtempSync(join(tmpdir(), 'pd2fa-'));
  const script = `<?php
    define('DB_PASS','x'); define('DB_NAME','y'); define('DB_USER','z');
    define('PD_2FA_KEY_DIR', ${JSON.stringify(keyDir)});
    require ${JSON.stringify(join(ROOT, 'includes', 'twofa.php'))};
    $secret = pd_base32_encode('12345678901234567890');
    $vec = [59=>'94287082', 1111111109=>'07081804', 1111111111=>'14050471', 1234567890=>'89005924', 2000000000=>'69279037', 20000000000=>'65353130'];
    foreach ($vec as $t=>$exp) { if (pd_totp_code($secret, intdiv($t,30), 8) !== $exp) { echo "RFC fout bij $t"; exit(1); } }
    $s = pd_2fa_generate_secret(); $now = 1700000000; $c = intdiv($now,30);
    if (pd_totp_verify($s, pd_totp_code($s,$c), 0, $now) !== $c) { echo 'goede code geweigerd'; exit(1); }
    if (pd_totp_verify($s, pd_totp_code($s,$c), $c, $now) !== null) { echo 'replay toegestaan'; exit(1); }
    if (pd_totp_verify($s, pd_totp_code($s,$c-2), 0, $now) !== null) { echo 'te oude code toegestaan'; exit(1); }
    if (pd_totp_verify($s, '12345', 0, $now) !== null) { echo 'korte code toegestaan'; exit(1); }
    if (pd_base32_decode($s) === '' || strlen(pd_base32_decode($s)) !== 20) { echo 'base32'; exit(1); }
    $enc = pd_2fa_encrypt($s);
    if (strpos($enc, $s) !== false || pd_2fa_decrypt($enc) !== $s) { echo 'versleuteling'; exit(1); }
    if (pd_2fa_decrypt('v2:AAAA') !== null) { echo 'ontsleutelen van rommel'; exit(1); }
    echo 'OK';`;
  // het sleutelbestand komt in een tijdelijke map, niet in de repository
  const r = spawnSync('php', ['-r', script.replace(/^<\?php/, '')], { encoding: 'utf8', cwd: ROOT });
  const keyCreated = existsSync(join(keyDir, 'twofa_key.php'));
  rmSync(keyDir, { recursive: true, force: true });
  assert.equal(r.stdout.trim(), 'OK', r.stdout + r.stderr);
  assert.ok(keyCreated, 'the key file must be created automatically');
  assert.equal(existsSync(join(ROOT, 'data', 'twofa_key.php')), false, 'the test must not leave a key file in the repository');
});

// ── Infoscherm ─────────────────────────────────────────────────────────
test('infoscreen: feed and weather parsers, URL guard (tests/infoscreen_test.php)', { skip: hasPhp ? false : 'PHP not installed on this computer' }, () => {
  const r = spawnSync('php', [join(ROOT, 'tests', 'infoscreen_test.php')], { encoding: 'utf8', cwd: ROOT });
  assert.equal(r.status, 0, r.stdout + r.stderr);
});

test('infoscreen: the Berichten-API needs a key and the Teams endpoint a valid HMAC signature, both rate-limited', () => {
  const push = read('api/infoscreen_push.php');
  assert.match(push, /pd_is_api_find_key\(\$db, pd_is_api_request_key\(\)\)/);
  assert.match(push, /if \(!\$keyRow\) \{[\s\S]*?401/);
  assert.match(push, /pd_is_api_rate_ok/);
  const teams = read('api/infoscreen_teams.php');
  assert.match(teams, /if \(!pd_is_teams_verify\(\$secret, \$raw, \$auth\)\) \{\s*pd_teams_reply\(401/);
  assert.match(teams, /pd_is_api_rate_ok/);
  const lib = read('includes/infoscreen_api.php');
  assert.match(lib, /hash_equals\(\$expected, \$m\[1\]\)/, 'HMAC compared in constant time');
  assert.match(lib, /hash\('sha256', \$key\)/, 'only the hash of an API key is stored');
  assert.ok(existsSync(join(ROOT, 'api', '.htaccess')), 'api/.htaccess passes the Authorization header to PHP');
});

test('infoscreen: API and pages need a login, the cron script only runs from the command line', () => {
  assert.match(read('api/infoscreen_data.php'), /empty\(\$_SESSION\['user_id'\]\)/);
  assert.match(read('infoscherm.php'), /isset\(\$_SESSION\['user_id'\]\)/);
  assert.match(read('admin/infoscreen_manage.php'), /requireAdmin\(\);\s*requireAdminFeature\('manage_infoscreen'\)/);
  assert.match(read('admin/infoscreen_manage.php'), /pd_infoscreen_csrf_ok/);
  assert.match(read('cron/cron_infoscreen.php'), /php_sapi_name\(\) !== 'cli'/);
});

test('infoscreen: long messages are made smaller to fit, the forms show a character counter', () => {
  assert.match(read('infoscherm.php'), /function fitText\(/);
  assert.match(read('infoscherm.php'), /var\(--fit\)/);
  assert.match(read('admin/infoscreen_manage.php'), /pd_infoscreen_text_counter_js\(\)/);
  assert.match(read('infoscherm_berichten.php'), /pd_infoscreen_text_counter_js\(\)/);
});

test('infoscreen website source: the preview needs an admin with the infoscreen right and a CSRF token', () => {
  const api = read('admin/api/infoscreen_web_preview.php');
  assert.match(api, /hasAdminFeature\('manage_infoscreen'\)/);
  assert.match(api, /pd_infoscreen_csrf_ok/);
  assert.match(read('includes/infoscreen_web.php'), /pd_infoscreen_http_get\(/); // SSRF-guarded fetch only
  assert.doesNotMatch(read('includes/infoscreen_web.php'), /curl_init|file_get_contents\(\s*\$url/);
});

test('cron: no shared key in the code, every web cron entry checks the installation key', () => {
  const files = ['cron_endpoint.php', 'cron/cleanup_tokens_cron.php', 'cron/daily_reset_cron.php',
    'cron/cron_auto_checkout.php', 'cron/cron_auto_delete.php', 'cron/cron_reset_expired_sub_status.php'];
  for (const f of files) {
    assert.match(read(f), /pd_cron_require_key\(/, `${f} must call pd_cron_require_key()`);
    assert.doesNotMatch(read(f), /INZ8U56BCZ/, `${f} still contains the old shared key`);
  }
  assert.match(read('admin/cron_manage.php'), /requireAdmin\(\);\s*requireAdminFeature\('manage_system_config'\)/);
  assert.match(read('admin/cron_manage.php'), /pd_infoscreen_csrf_ok/);
});

test('infoscreen editors: the page limits messages to the user\'s own locations', () => {
  const page = read('infoscherm_berichten.php');
  assert.match(page, /pd_infoscreen_editor_scope\(/);
  assert.match(page, /pd_infoscreen_scope_allows\(/);
  assert.match(page, /pd_infoscreen_csrf_ok/);
  assert.match(page, /pd_infoscreen_locs_from_post\(\$pickable, \$scope\['all'\]\)/);
  assert.match(read('admin/users_manage.php'), /'infoscreenEditor' => \$infoscreenEditor/);
});

// ── PHP syntax ─────────────────────────────────────────────────────────
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
