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
  const migrations = read('includes/migrations.php');
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

// ── PHP syntax ─────────────────────────────────────────────────────────
const hasPhp = spawnSync('php', ['-v']).status === 0;
test('all PHP files have valid syntax', { skip: hasPhp ? false : 'PHP not installed on this computer' }, () => {
  const r = spawnSync(process.execPath, [join(ROOT, 'scripts', 'lint.mjs')], { encoding: 'utf8' });
  assert.equal(r.status, 0, r.stdout + r.stderr);
});
