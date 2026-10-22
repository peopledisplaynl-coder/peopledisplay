// scripts/lib/files.mjs
// Which files belong on which server. Shared by the deploy script and the checks,
// so "what gets uploaded" is defined in exactly one place.

import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// Developer-only files: never uploaded to any server.
export const DEV_ONLY = [
  '.git', '.gitignore', '.gitattributes', '.claude/', '.vscode/',
  'node_modules/', 'tests/', 'scripts/', 'docs/', 'tools/',
  'package.json', 'package-lock.json', 'CLAUDE.md',
  'deploy.config.json', 'deploy.config.example.json', '.deploy-state/',
  'update_files.txt', 'Claude outputs/',
];

// Server-owned files: configuration and customer data. Never overwritten.
export const PROTECTED = [
  'includes/db_config.php', 'admin/db_config.php', 'admin/config.json',
  'uploads/', 'logs/', 'tmp/', 'backups/', 'data/', '.installed',
];

// Extra exclusions for an installed site (test / live). Mirrors EXCLUDE_FROM_ZIP
// in build_update.php: installers, server config and project docs stay off a
// running installation.
export const SITE_ONLY_EXCLUDES = [
  'includes/config.php', 'config.example.php',
  'install.php', 'install.sql', 'install/',
  '.htaccess',
  'README.md', 'CHANGELOG.md', 'INSTALLATION_GUIDE.md', 'PACKAGE_READY.md',
  'SCHEMA_COMPARISON.md', 'ADMIN_FEATURES_PLAN.md',
  'update_debug.php', 'dir.php', 'build_update.php',
];

// Profiles: "site" = a running PeopleDisplay (test.peopledisplay.nl, mijn.peopledisplay.nl);
// "golden" = the master copy in admin.peopledisplay.nl/install/ that build_update.php zips.
export const PROFILES = {
  site: [...DEV_ONLY, ...PROTECTED, ...SITE_ONLY_EXCLUDES],
  golden: [...DEV_ONLY, ...PROTECTED],
};

export function isExcluded(path, excludes) {
  return excludes.some((ex) => (ex.endsWith('/') ? path.startsWith(ex) : path === ex || path.startsWith(ex + '/')));
}

// All project files git knows about: tracked plus new, not-ignored files.
// Deleted-but-not-yet-committed files are skipped.
export function projectFiles() {
  const out = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], {
    cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024,
  });
  const deleted = new Set(
    execFileSync('git', ['ls-files', '--deleted', '-z'], { cwd: ROOT, encoding: 'utf8' }).split('\0').filter(Boolean),
  );
  return [...new Set(out.split('\0').filter(Boolean))].filter((f) => !deleted.has(f)).sort();
}

export function filesForProfile(profile) {
  const excludes = PROFILES[profile];
  if (!excludes) throw new Error(`Unknown profile "${profile}" (use "site" or "golden")`);
  return projectFiles().filter((f) => !isExcluded(f, excludes));
}

// Files changed since the most recent git tag (committed and uncommitted),
// limited to what a delta update may contain. Null when there is no tag yet.
export function changedSinceLastTag() {
  let tag;
  try {
    tag = execFileSync('git', ['describe', '--tags', '--abbrev=0'], { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null;
  }
  const committed = execFileSync('git', ['-c', 'core.quotepath=off', 'diff', '--name-only', '--diff-filter=ACMR', tag, '--'], { cwd: ROOT, encoding: 'utf8' });
  const untracked = execFileSync('git', ['-c', 'core.quotepath=off', 'ls-files', '--others', '--exclude-standard'], { cwd: ROOT, encoding: 'utf8' });
  const files = [...new Set((committed + '\n' + untracked).split(/\r?\n/).filter(Boolean))];
  return { tag, files: files.filter((f) => !isExcluded(f, PROFILES.site)).sort() };
}
