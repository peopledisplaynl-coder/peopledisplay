#!/usr/bin/env node
// scripts/lint.mjs
// Syntax check (php -l) of every PHP file that ends up on a server.
// Needs PHP on this computer (`php -v`). Without PHP it says so and exits 0,
// so the other checks and the deploy keep working.
// Usage: npm run lint

import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { ROOT, filesForProfile } from './lib/files.mjs';

const probe = spawnSync('php', ['-v'], { encoding: 'utf8' });
if (probe.status !== 0) {
  console.log('PHP niet gevonden op deze computer — PHP-syntaxcontrole overgeslagen.');
  process.exit(0);
}

const files = filesForProfile('golden').filter((f) => f.endsWith('.php') && !f.startsWith('tcpdf/'));
const errors = [];
for (const f of files) {
  const r = spawnSync('php', ['-l', join(ROOT, f)], { encoding: 'utf8' });
  if (r.status !== 0) errors.push(`${f}\n${(r.stdout + r.stderr).trim()}`);
}
if (errors.length) {
  console.error(errors.join('\n\n'));
  console.error(`\n✗ ${errors.length} PHP-bestand(en) met syntaxfouten.`);
  process.exit(1);
}
console.log(`✓ ${files.length} PHP-bestanden zonder syntaxfouten.`);
