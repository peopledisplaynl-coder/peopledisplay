#!/usr/bin/env node
// docs/tools/screenshots.mjs
// Makes every screenshot used in the manuals (docs/images/*.png) from a running demo
// installation filled by docs/tools/demo-seed.php. Fictional data only.
//
//   PD_BASE=http://localhost:8080 [PD_INSTALL_BASE=http://localhost:8081] node docs/tools/screenshots.mjs [name …]
//
// PD_INSTALL_BASE is an untouched copy of the app without admin/db_config.php, used for
// the installer screenshots (it gets installed into the database PD_INSTALL_DB).
// Pass names to redo only those screenshots. See docs/README.md for the full recipe.
// PD_CDN_DIR (optional): a folder with node_modules/ holding the CDN packages some pages load
// (@tabler/icons-webfont, qrcodejs) — for machines without internet access.

import { chromium } from 'playwright-core';
import { mkdirSync, existsSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = join(ROOT, 'docs', 'images');
const BASE = process.env.PD_BASE || 'http://localhost:8080';
const INSTALL_BASE = process.env.PD_INSTALL_BASE || '';
const INSTALL_DB = { host: process.env.PD_INSTALL_DB_HOST || '127.0.0.1', name: process.env.PD_INSTALL_DB || 'pd_install',
  user: process.env.PD_INSTALL_DB_USER || 'pd', pass: process.env.PD_INSTALL_DB_PASS || 'pd' };
const PASSWORD = 'Demo2026!';
const only = new Set(process.argv.slice(2));
mkdirSync(OUT, { recursive: true });

const DESKTOP = { width: 1366, height: 860 };
const PHONE = { width: 390, height: 844 };

// PD_HOST_RULES (optional): Chromium host-resolver rules, so the demo can run under a
// fictional domain, e.g. "MAP demo.peopledisplay.test 127.0.0.1:8080".
const browser = await chromium.launch({ args: process.env.PD_HOST_RULES ? [`--host-resolver-rules=${process.env.PD_HOST_RULES}`] : [] });
const CDN_DIR = process.env.PD_CDN_DIR || '';
const MIME = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', woff: 'font/woff', ttf: 'font/ttf' };

// Serve CDN files from PD_CDN_DIR/node_modules when that folder is given.
async function offlineCdn(ctx) {
  if (!CDN_DIR) return;
  await ctx.route(/^https:\/\/(cdn\.jsdelivr\.net\/npm|cdnjs\.cloudflare\.com\/ajax\/libs)\//, async (route) => {
    const url = new URL(route.request().url());
    let pkg, rest;
    if (url.hostname === 'cdn.jsdelivr.net') {
      const m = url.pathname.match(/^\/npm\/((?:@[^/]+\/)?[^/@]+)(?:@[^/]+)?\/(.*)$/);
      if (m) [, pkg, rest] = m;
    } else {
      const m = url.pathname.match(/^\/ajax\/libs\/([^/]+)\/[^/]+\/(.*)$/);
      if (m) [, pkg, rest] = m;
    }
    const candidates = pkg ? [join(CDN_DIR, 'node_modules', pkg, rest), join(CDN_DIR, 'node_modules', pkg, 'dist', rest)] : [];
    const file = candidates.find((f) => existsSync(f));
    if (!file) return route.abort();
    await route.fulfill({ body: readFileSync(file), contentType: MIME[file.split('.').pop()] || 'application/octet-stream' });
  });
}
const sessions = {};
const made = [];
const problems = [];

async function pageFor(user, viewport = DESKTOP) {
  const key = `${user}@${viewport.width}`;
  if (!sessions[key]) {
    const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, locale: 'nl-NL', timezoneId: 'Europe/Amsterdam',
      isMobile: viewport === PHONE, hasTouch: viewport === PHONE });
    await offlineCdn(ctx);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => problems.push(`${page.url()}: ${e.message}`));
    page.on('dialog', (d) => d.accept());
    if (user) {
      await page.goto(`${BASE}/login.php`);
      await page.fill('input[name=username]', user);
      await page.fill('input[name=password]', PASSWORD);
      await Promise.all([page.waitForNavigation(), page.press('input[name=password]', 'Enter')]);
    }
    sessions[key] = page;
  }
  return sessions[key];
}

async function open(page, path, wait = 1800) {
  await page.goto(`${BASE}/${path}`);
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(wait);
}

async function save(name, page, opts = {}) {
  const path = join(OUT, `${name}.png`);
  if (opts.selector) {
    const el = page.locator(opts.selector).first();
    await el.scrollIntoViewIfNeeded();
    if (opts.pad) {
      const box = await el.boundingBox();
      const p = opts.pad;
      await page.screenshot({ path, clip: { x: Math.max(0, box.x - p), y: Math.max(0, box.y - p), width: box.width + 2 * p, height: box.height + 2 * p } });
    } else {
      await el.screenshot({ path });
    }
  } else if (opts.clip) {
    await page.screenshot({ path, clip: opts.clip });
  } else {
    await page.screenshot({ path, fullPage: !!opts.full });
  }
  made.push(name);
}

const shots = [];
const shot = (name, fn) => shots.push({ name, fn });

// ── Gebruikershandleiding ──────────────────────────────────────────────
shot('login', async () => {
  const p = await pageFor(null);
  await open(p, 'login.php', 600);
  await save('login', p);
});
shot('frontpage', async () => {
  const p = await pageFor('admin');
  await open(p, 'frontpage.php', 800);
  await save('frontpage', p);
});
shot('aanmeldscherm', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await save('aanmeldscherm', p);
});
shot('header', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await save('header', p, { clip: { x: 0, y: 0, width: DESKTOP.width, height: 74 } });
});
shot('filters', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await save('filters', p, { clip: { x: 0, y: 112, width: DESKTOP.width, height: 66 } });
});
shot('menu-locaties', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await p.click('#toggle-menu-btn');
  await p.waitForTimeout(700);
  const h = await p.evaluate(() => {
    const f = document.querySelector('#search-input, input[placeholder*="Zoek"]');
    return f ? Math.round(f.getBoundingClientRect().bottom + 16) : 260;
  });
  await save('menu-locaties', p, { clip: { x: 0, y: 0, width: DESKTOP.width, height: Math.min(h, 420) } });
  await p.click('#toggle-menu-btn');
});
shot('kaart', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await save('kaart', p, { selector: '.employee-card:has-text("Daan Visser")', pad: 8 });
});
shot('kaart-substatus', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await save('kaart-substatus', p, { selector: '.employee-card:has-text("Finn Meijer")', pad: 8 });
});
shot('bezoekers-blok', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  const box = await p.evaluate(() => {
    const els = [...document.querySelectorAll('div')].filter((d) => /Verwachte Bezoekers|Bezoekers Binnen/.test(d.firstElementChild?.innerText || '') && d.getBoundingClientRect().width < 700);
    if (!els.length) return null;
    const r = els.map((e) => e.getBoundingClientRect());
    return { x: Math.min(...r.map((b) => b.left)), y: Math.min(...r.map((b) => b.top)), r: Math.max(...r.map((b) => b.right)), b: Math.max(...r.map((b) => b.bottom)) };
  });
  if (!box) throw new Error('visitor blocks not found');
  await save('bezoekers-blok', p, { clip: { x: Math.max(0, box.x - 8), y: box.y - 8, width: box.r - box.x + 16, height: box.b - box.y + 16 } });
});
shot('tot-wanneer', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await p.locator('.employee-card:has-text("Bram Jansen") button.extra-btn.vakantie').click();
  await p.waitForTimeout(900);
  await save('tot-wanneer', p);
  await p.keyboard.press('Escape');
  await open(p, 'index.php', 500); // modal closed without saving
});
shot('pion', async () => {
  const p = await pageFor('admin');
  await open(p, 'index.php', 2500);
  await p.locator('.employee-card:has-text("Daan Visser") button.manual-location-btn').click();
  await p.waitForTimeout(900);
  await save('pion', p);
  await open(p, 'index.php', 500);
});
shot('overzicht', async () => {
  const p = await pageFor('admin');
  await open(p, 'overzicht.php', 2500);
  await save('overzicht', p);
});
shot('inuitbord', async () => {
  const p = await pageFor('admin');
  await open(p, 'inuitbord.php', 2500);
  await save('inuitbord', p);
});
shot('bhv-overzicht', async () => {
  const p = await pageFor('admin');
  await open(p, 'bhv-print/bhv-print.html', 2500);
  await save('bhv-overzicht', p);
});
shot('profiel', async () => {
  const p = await pageFor('admin');
  await open(p, 'user/profile.php', 1200);
  await save('profiel', p, { full: true });
});
shot('mobiel', async () => {
  const p = await pageFor('admin', PHONE);
  await open(p, 'index.php', 2500);
  await save('mobiel', p);
});
shot('bezoeker-registratie', async () => {
  const p = await pageFor(null);
  await open(p, 'visitor_register.php', 1200);
  await save('bezoeker-registratie', p, { full: true });
});

// ── Beheerdershandleiding ──────────────────────────────────────────────
const adminPage = (name, path, opts = {}) => shot(name, async () => {
  const p = await pageFor('admin');
  await open(p, path, opts.wait ?? 1500);
  if (opts.before) await opts.before(p);
  await save(name, p, opts);
});
adminPage('dashboard', 'admin/dashboard.php', { full: true });
adminPage('medewerkers', 'admin/employees_manage.php');
adminPage('medewerker-nieuw', 'admin/employees_manage.php', {
  before: async (p) => { await p.getByText('Nieuwe Medewerker Toevoegen').first().click(); await p.waitForTimeout(800); },
});
adminPage('medewerker-bewerken', 'admin/employees_manage.php', {
  before: async (p) => { await p.locator('tr:has-text("Daan") >> text=Bewerken').first().click(); await p.waitForTimeout(1200); },
});
adminPage('gebruikers', 'admin/users_manage.php');
adminPage('gebruiker-bewerken', 'admin/users_manage.php', {
  before: async (p) => { await p.locator('tr:has-text("receptie") button').first().click(); await p.waitForTimeout(1200); },
  full: true,
});
adminPage('gebruiker-features', 'admin/users_manage.php', {
  before: async (p) => {
    await p.locator('tr:has-text("receptie") button').first().click();
    await p.waitForTimeout(1200);
    await p.getByText('Features', { exact: false }).filter({ hasNotText: 'Features Beheren' }).first().click();
    await p.waitForTimeout(800);
  },
  full: true,
});
adminPage('locaties', 'admin/locations_manage.php');
adminPage('locatie-volgorde', 'admin/locations_order.php');
adminPage('afdelingen', 'admin/afdelingen_manage.php');
adminPage('bezoekers-beheer', 'admin/visitors_manage.php');
adminPage('bezoeker-emails', 'admin/visitor_email_config.php');
adminPage('badges', 'admin/badges_manage.php');
adminPage('bulk-acties', 'admin/bulk_actions.php');
adminPage('online-gebruikers', 'admin/online_users.php', { wait: 2500 });
adminPage('systeemconfiguratie', 'admin/config_manage.php');
adminPage('naamweergave', 'admin/name_display_config.php');
adminPage('kiosk-tokens', 'admin/kiosk_tokens_manage.php');
adminPage('audit-log', 'admin/audit_log.php?action=STATUS_CHANGE&period=7d');
adminPage('substatus-datums', 'admin/substatus_date_settings.php');
adminPage('licentie', 'admin/license_management.php', { full: true });
adminPage('backup', 'admin/backup.php');

// ── Installatiehandleiding ─────────────────────────────────────────────
shot('install-stappen', async () => {
  if (!INSTALL_BASE) throw new Error('PD_INSTALL_BASE not set — installer screenshots skipped');
  const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 } });
  const p = await ctx.newPage();
  const step = async (n, name) => { await p.waitForTimeout(500); await save(name, p, { full: true }); };
  await p.goto(`${INSTALL_BASE}/install.php`);
  await step(1, 'install-1-controle');
  await p.click('button[type=submit]');
  await p.waitForLoadState();
  await p.check('#eula_checkbox');
  await step(2, 'install-2-voorwaarden');
  await p.click('#eulaBtn');
  await p.waitForLoadState();
  await step(3, 'install-3-licentie');
  await p.click('#starterBtn');
  await p.waitForLoadState();
  await p.fill('input[name=db_host]', INSTALL_DB.host);
  await p.fill('input[name=db_name]', INSTALL_DB.name);
  await p.fill('input[name=db_user]', INSTALL_DB.user);
  await p.fill('input[name=db_pass]', INSTALL_DB.pass);
  await step(4, 'install-4-database');
  await p.click('button[type=submit]');
  await p.waitForLoadState();
  await step(5, 'install-5-tabellen');
  await p.click('button[type=submit]');
  await p.waitForLoadState();
  if (await p.locator('input[name=admin_username]').count() === 0) {
    await p.click('button[type=submit]');
    await p.waitForLoadState();
  }
  await p.fill('input[name=admin_display]', 'Beheerder');
  await p.fill('input[name=admin_email]', 'admin@voorbeeldschool.nl');
  await p.fill('input[name=admin_password]', PASSWORD);
  const confirm = p.locator('input[name=admin_password_confirm], input[name=admin_password2]');
  if (await confirm.count()) await confirm.fill(PASSWORD);
  await step(6, 'install-6-beheerder');
  await p.click('button[type=submit]');
  await p.waitForLoadState();
  await step(7, 'install-7-voltooien');
  await p.locator('button[type=submit]').last().click();
  await p.waitForLoadState();
  await step(8, 'install-8-klaar');
  await ctx.close();
});

// ── Run ────────────────────────────────────────────────────────────────
for (const s of shots) {
  if (only.size && !only.has(s.name)) continue;
  try {
    await s.fn();
  } catch (e) {
    problems.push(`${s.name}: ${e.message.split('\n')[0]}`);
  }
}
await browser.close();
console.log(`✓ ${made.length} screenshots in docs/images/`);
if (problems.length) {
  console.log('Problems:\n  ' + problems.join('\n  '));
  process.exitCode = 1;
}
