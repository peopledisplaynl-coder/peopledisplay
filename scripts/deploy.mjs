#!/usr/bin/env node
// scripts/deploy.mjs
// Uploads PeopleDisplay to a server by SFTP or FTP(S) — only the files that changed since the
// previous upload to that same target.
//
//   npm run deploy                         → test.peopledisplay.nl (target "test")
//   npm run deploy -- --target=golden      → admin.peopledisplay.nl/install/ (master copy)
//   npm run deploy -- --target=live        → mijn.peopledisplay.nl (asks for confirmation)
//
// Options:
//   --dry-run       show what would be uploaded, upload nothing
//   --full          ignore the upload state and upload every file
//   --baseline=REF  first use on a server that is already up to date with git commit REF
//                   (e.g. --baseline=HEAD): only files that differ from REF are uploaded
//   --skip-checks   do not run `npm test` first
//   --yes           no confirmation question for targets with "confirm": true
//   --no-update-list  (golden) do not write/upload update_files.txt
//
// Credentials live in deploy.config.json (git-ignored; copy deploy.config.example.json).
// The script never uploads server config, customer data or developer files — see
// scripts/lib/files.mjs — and never deletes anything on the server.

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, posix } from 'node:path';
import { spawnSync } from 'node:child_process';
import { createInterface } from 'node:readline/promises';
import { ROOT, filesForProfile, changedSinceLastTag } from './lib/files.mjs';

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, v] = a.replace(/^--/, '').split('=');
    return [k, v ?? true];
  }),
);
const targetName = args.target || 'test';

function fail(msg) {
  console.error(`\n✗ ${msg}\n`);
  process.exit(1);
}

// ── Config ─────────────────────────────────────────────────────────────
const configPath = join(ROOT, 'deploy.config.json');
if (!existsSync(configPath)) {
  fail('deploy.config.json ontbreekt. Kopieer deploy.config.example.json naar deploy.config.json en vul je FTP-gegevens in.');
}
const config = JSON.parse(readFileSync(configPath, 'utf8'));
const target = config[targetName];
if (!target) fail(`Target "${targetName}" staat niet in deploy.config.json (beschikbaar: ${Object.keys(config).join(', ')}).`);
for (const key of ['host', 'user', 'password', 'remoteDir']) {
  if (!target[key]) fail(`"${key}" ontbreekt bij target "${targetName}" in deploy.config.json.`);
}
const profile = target.profile || (targetName === 'golden' ? 'golden' : 'site');
const remoteRoot = target.remoteDir.replace(/\/+$/, '') || '/';

// ── Checks first ───────────────────────────────────────────────────────
if (!args['skip-checks']) {
  console.log('Controles draaien (npm test)…');
  const r = spawnSync(process.execPath, ['--test', 'tests/*.test.mjs'], { cwd: ROOT, stdio: 'inherit' });
  if (r.status !== 0) fail('Controles mislukt — er is niets geüpload. (Overslaan kan met --skip-checks.)');
}

// ── What changed? ──────────────────────────────────────────────────────
const stateDir = join(ROOT, '.deploy-state');
const statePath = join(stateDir, `${targetName}.json`);
const state = !args.full && existsSync(statePath) ? JSON.parse(readFileSync(statePath, 'utf8')) : { files: {} };

// Text files are hashed with normalised line endings, so a Windows CRLF checkout
// does not count as a change.
const hashBuffer = (buf) => {
  const isText = !buf.subarray(0, 8000).includes(0);
  const data = isText ? Buffer.from(buf.toString('latin1').replace(/\r\n/g, '\n'), 'latin1') : buf;
  return createHash('sha1').update(data).digest('hex');
};
const sha1 = (file) => hashBuffer(readFileSync(join(ROOT, file)));
const all = filesForProfile(profile);
const hashes = Object.fromEntries(all.map((f) => [f, sha1(f)]));

if (typeof args.baseline === 'string') {
  let marked = 0;
  for (const f of all) {
    const r = spawnSync('git', ['show', `${args.baseline}:${f}`], { cwd: ROOT, maxBuffer: 256 * 1024 * 1024 });
    if (r.status === 0) {
      state.files[f] = hashBuffer(r.stdout);
      marked++;
    }
  }
  console.log(`Baseline ${args.baseline}: ${marked} bestanden gemarkeerd als al aanwezig op ${targetName}.`);
}
const changed = all.filter((f) => state.files[f] !== hashes[f]);

// For the golden master: a fresh update_files.txt for build_update.php's delta ZIP.
let updateList = null;
if (profile === 'golden' && !args['no-update-list']) {
  const since = changedSinceLastTag();
  if (since) {
    updateList = `# Gewijzigd sinds ${since.tag} — gegenereerd door npm run deploy\n` + since.files.join('\n') + '\n';
    writeFileSync(join(ROOT, 'update_files.txt'), updateList);
  } else {
    console.log('ℹ Nog geen git-tag gevonden: update_files.txt niet gemaakt (vul hem deze keer zelf in build_update.php).');
  }
}

console.log(`\nTarget: ${targetName} (${target.host}${remoteRoot}) — profiel "${profile}"`);
console.log(`${all.length} bestanden in het project, ${changed.length} gewijzigd sinds de vorige upload.`);
if (changed.length) console.log(changed.map((f) => '  ' + f).join('\n'));
if (updateList) console.log(`update_files.txt: ${updateList.split('\n').length - 2} bestanden sinds de laatste tag.`);

if (args['dry-run']) {
  console.log('\n(dry-run — niets geüpload)');
  process.exit(0);
}
if (!changed.length && !updateList) {
  console.log('\nNiets te uploaden.');
  process.exit(0);
}

if (target.confirm && !args.yes) {
  const rl = createInterface({ input: process.stdin, output: process.stdout });
  const answer = await rl.question(`\nDit is ${targetName.toUpperCase()}. Typ "${targetName}" om te uploaden: `);
  rl.close();
  if (answer.trim() !== targetName) fail('Afgebroken — niets geüpload.');
}

// ── Upload ─────────────────────────────────────────────────────────────
// "protocol": "sftp" (SSH, port 22 — e.g. Strato's *.ssh.*.strato.hosting) or "ftp"
// (FTP/FTPS, port 21). Without a protocol: sftp when the host or port says SSH.
const protocol = target.protocol || (/(^|\.)ssh\./.test(target.host) || target.port === 22 ? 'sftp' : 'ftp');

async function openTransport() {
  if (protocol === 'sftp') {
    let SftpClient;
    try {
      SftpClient = (await import('ssh2-sftp-client')).default;
    } catch {
      fail('ssh2-sftp-client is niet geïnstalleerd. Draai eerst: npm install');
    }
    const sftp = new SftpClient();
    await sftp.connect({
      host: target.host,
      port: target.port || 22,
      username: target.user,
      password: target.password,
      readyTimeout: 30_000,
    });
    const made = new Set();
    return {
      async upload(local, remote) {
        const dir = posix.dirname(remote);
        if (!made.has(dir)) {
          if (!(await sftp.exists(dir))) await sftp.mkdir(dir, true);
          made.add(dir);
        }
        await sftp.fastPut(local, remote);
      },
      close: () => sftp.end().catch(() => {}),
    };
  }

  let ftp;
  try {
    ftp = await import('basic-ftp');
  } catch {
    fail('basic-ftp is niet geïnstalleerd. Draai eerst: npm install');
  }
  const client = new ftp.Client(30_000);
  await client.access({
    host: target.host,
    port: target.port || 21,
    user: target.user,
    password: target.password,
    secure: target.secure ?? true,
    secureOptions: target.rejectUnauthorized === false ? { rejectUnauthorized: false } : undefined,
  });
  let cwd = null;
  return {
    async upload(local, remote) {
      const dir = posix.dirname(remote);
      if (dir !== cwd) {
        await client.ensureDir(dir);
        cwd = dir;
      }
      await client.uploadFrom(local, posix.basename(remote));
    },
    close: () => client.close(),
  };
}

const queue = [...changed].sort((a, b) => posix.dirname(a).localeCompare(posix.dirname(b)) || a.localeCompare(b));
if (updateList) queue.push('update_files.txt');

const saveState = () => {
  mkdirSync(stateDir, { recursive: true });
  writeFileSync(statePath, JSON.stringify(state, null, 2));
};

console.log(`\nVerbinden met ${target.host} (${protocol.toUpperCase()})…`);
let transport;
const uploaded = [];
try {
  transport = await openTransport();
  for (const f of queue) {
    await transport.upload(join(ROOT, f), posix.join(remoteRoot, f));
    if (f !== 'update_files.txt') {
      state.files[f] = hashes[f];
      uploaded.push(f);
      if (uploaded.length % 25 === 0) saveState(); // an interrupted upload resumes where it stopped
    }
    process.stdout.write(`  ↑ ${f}\n`);
  }
} catch (e) {
  saveState();
  const hint = uploaded.length === 0
    ? `\n  Controleer host, gebruikersnaam, wachtwoord en protocol ("protocol": "sftp" of "ftp") in deploy.config.json.`
    : `\n  ${uploaded.length} bestanden wel geüpload; draai het commando opnieuw om verder te gaan.`;
  fail(`Upload gestopt: ${e.message}${hint}`);
} finally {
  await transport?.close();
}

state.lastDeploy = new Date().toISOString();
writeFileSync(statePath, JSON.stringify(state, null, 2));
console.log(`\n✓ ${uploaded.length} bestanden geüpload naar ${targetName}.`);
