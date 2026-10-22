#!/usr/bin/env node
// scripts/changelog.mjs
// Regenerates CHANGELOG.md from version.json, in exactly the same format as
// generateChangelog() in build_update.php on admin.peopledisplay.nl, so the
// file in the repository and the one build_update.php writes never drift apart.
//
// Usage:  npm run changelog            (writes CHANGELOG.md)
//         node scripts/changelog.mjs --check   (exit 1 when CHANGELOG.md is out of date)

import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

export function renderChangelog(versionData) {
  let md = '# PeopleDisplay Changelog\n\n';
  md += '> Automatisch gegenereerd — niet handmatig bewerken.\n\n';
  for (const release of versionData.changelog ?? []) {
    md += `## v${release.version} — ${release.date}\n\n`;
    for (const change of release.changes ?? []) md += `- ${change}\n`;
    md += '\n';
  }
  return md;
}

export function expectedChangelog() {
  const data = JSON.parse(readFileSync(join(root, 'version.json'), 'utf8'));
  return renderChangelog(data);
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const target = join(root, 'CHANGELOG.md');
  const md = expectedChangelog();
  if (process.argv.includes('--check')) {
    const current = readFileSync(target, 'utf8').replace(/\r\n/g, '\n');
    if (current !== md) {
      console.error('CHANGELOG.md is out of date — run: npm run changelog');
      process.exit(1);
    }
    console.log('CHANGELOG.md is up to date.');
  } else {
    writeFileSync(target, md);
    console.log('CHANGELOG.md written from version.json.');
  }
}
