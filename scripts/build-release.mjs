#!/usr/bin/env node
// scripts/build-release.mjs
// Builds the installation ZIP for new customers — the same content as
// "Genereer Installatie ZIP" in build_update.php (profile "release" in
// scripts/lib/files.mjs) — plus the release notes for that version.
//
//   npm run release:build                     → dist/peopledisplay-starter.zip
//   node scripts/build-release.mjs --tag=v2.1.9   (fails when the tag ≠ version.json)
//
// Output in dist/ (git-ignored):
//   peopledisplay-starter.zip          files at the root of the ZIP, like the build_update ZIP
//   peopledisplay-starter.zip.sha256
//   release-notes.md                   the changelog lines of this version
//
// Used by .github/workflows/release.yml; runs the same on Windows (pure Node, no zip tool).

import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { deflateRawSync } from 'node:zlib';
import { ROOT, filesForProfile, RELEASE_EMPTY_DIRS } from './lib/files.mjs';

export const ASSET_NAME = 'peopledisplay-starter.zip';

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
  const [k, v] = a.replace(/^--/, '').split('=');
  return [k, v ?? true];
}));

function fail(msg) {
  console.error(`✗ ${msg}`);
  process.exit(1);
}

// ── Version and tag ────────────────────────────────────────────────────
const versionData = JSON.parse(readFileSync(join(ROOT, 'version.json'), 'utf8'));
const version = versionData.version;
const php = readFileSync(join(ROOT, 'includes', 'version.php'), 'utf8');
const phpVersion = php.match(/define\('PD_CURRENT_VERSION',\s*'([^']+)'\)/)?.[1];
if (phpVersion !== version) fail(`includes/version.php (${phpVersion}) ≠ version.json (${version})`);
if (typeof args.tag === 'string') {
  const tagVersion = args.tag.replace(/^refs\/tags\//, '').replace(/^v/, '');
  if (tagVersion !== version) {
    fail(`Tag ${args.tag} hoort niet bij deze code: version.json staat op ${version}. ` +
      'Zet de tag op de commit met de juiste versie (git tag -f vX.Y.Z) en push hem opnieuw.');
  }
}
const entry = (versionData.changelog ?? []).find((e) => e.version === version);
if (!entry || !entry.changes?.length) fail(`Geen changelog-regels voor ${version} in version.json.`);

// ── Minimal ZIP writer (deflate, no external tools) ────────────────────
const CRC_TABLE = new Uint32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function dosDateTime(date) {
  const time = (date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1);
  const day = ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
  return { time, day };
}

export function buildZip(entries) {
  // entries: [{ name, data: Buffer|null (null = folder), date }]
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const e of entries) {
    const name = Buffer.from(e.name, 'utf8');
    const isDir = e.data === null;
    const raw = isDir ? Buffer.alloc(0) : e.data;
    const deflated = isDir ? raw : deflateRawSync(raw, { level: 9 });
    const useDeflate = !isDir && deflated.length < raw.length;
    const body = useDeflate ? deflated : raw;
    const crc = crc32(raw);
    const { time, day } = dosDateTime(e.date);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6); // UTF-8 names
    local.writeUInt16LE(useDeflate ? 8 : 0, 8);
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(day, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(body.length, 18);
    local.writeUInt32LE(raw.length, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);
    locals.push(local, name, body);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(0x0314, 4); // made by: Unix, v2.0
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(useDeflate ? 8 : 0, 10);
    central.writeUInt16LE(time, 12);
    central.writeUInt16LE(day, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(body.length, 20);
    central.writeUInt32LE(raw.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt32LE(isDir ? ((0o40755 << 16) | 0x10) >>> 0 : (0o100644 << 16) >>> 0, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);

    offset += local.length + name.length + body.length;
  }
  const centralBuf = Buffer.concat(centrals);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuf.length, 12);
  end.writeUInt32LE(offset, 16);
  return Buffer.concat([...locals, centralBuf, end]);
}

// ── Build ──────────────────────────────────────────────────────────────
if (process.argv[1] && process.argv[1].endsWith('build-release.mjs')) {
  const files = filesForProfile('release');
  const now = new Date();
  const entries = [
    ...RELEASE_EMPTY_DIRS.map((d) => ({ name: d, data: null, date: now })),
    ...files.map((f) => ({ name: f, data: readFileSync(join(ROOT, f)), date: statSync(join(ROOT, f)).mtime })),
  ];
  const zip = buildZip(entries);

  const dist = join(ROOT, 'dist');
  mkdirSync(dist, { recursive: true });
  writeFileSync(join(dist, ASSET_NAME), zip);
  const sha = createHash('sha256').update(zip).digest('hex');
  writeFileSync(join(dist, `${ASSET_NAME}.sha256`), `${sha}  ${ASSET_NAME}\n`);

  const notes = [
    `PeopleDisplay ${version} — ${entry.date}`,
    '',
    ...entry.changes.map((c) => `- ${c}`),
    '',
    `**Installeren:** pak \`${ASSET_NAME}\` uit op je webhosting en open \`/install.php\` in je browser. ` +
      'Bestaande installaties worden bijgewerkt via de update-melding in het admin-dashboard.',
    '',
    `SHA-256: \`${sha}\``,
    '',
  ].join('\n');
  writeFileSync(join(dist, 'release-notes.md'), notes);
  writeFileSync(join(dist, 'version.txt'), version + '\n');

  console.log(`✓ dist/${ASSET_NAME}: v${version}, ${files.length} bestanden, ${(zip.length / 1024 / 1024).toFixed(1)} MB`);
}
