#!/usr/bin/env node
// docs/tools/build-docs.mjs
// Builds the PDF and Word versions of the manuals from the Markdown in docs/nl and docs/en.
//   PDF  : pandoc → standalone HTML (docs/tools/manual.css) → Chromium print (page numbers)
//   Word : pandoc → .docx with a table of contents
// Output: docs/pdf/*.pdf and docs/word/*.docx (committed, so they are on GitHub).
//
//   npm run docs:build            (needs pandoc and the Chromium used by playwright-core)

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const DOCS = join(ROOT, 'docs');
const version = JSON.parse(readFileSync(join(ROOT, 'version.json'), 'utf8')).version;

const MANUALS = [
  { dir: 'nl', file: 'gebruikershandleiding', toc: 'Inhoud', page: 'Pagina', ver: 'Versie' },
  { dir: 'nl', file: 'beheerdershandleiding', toc: 'Inhoud', page: 'Pagina', ver: 'Versie' },
  { dir: 'nl', file: 'installatiehandleiding', toc: 'Inhoud', page: 'Pagina', ver: 'Versie' },
  { dir: 'en', file: 'user-guide', toc: 'Contents', page: 'Page', ver: 'Version' },
  { dir: 'en', file: 'admin-guide', toc: 'Contents', page: 'Page', ver: 'Version' },
  { dir: 'en', file: 'installation-guide', toc: 'Contents', page: 'Page', ver: 'Version' },
];

for (const d of ['pdf', 'word']) mkdirSync(join(DOCS, d), { recursive: true });
const tmp = join(DOCS, '.build');
mkdirSync(tmp, { recursive: true });

const FILTER = ['--lua-filter', join(DOCS, 'tools', 'image-sizes.lua')];
const pandoc = (args) => execFileSync('pandoc', [...FILTER, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'inherit'] });

const browser = await chromium.launch();
for (const m of MANUALS) {
  const src = join(DOCS, m.dir, `${m.file}.md`);
  const res = join(DOCS, m.dir);

  // Word
  pandoc([src, '--from', 'markdown', '--toc', '--toc-depth=2', '--resource-path', res,
    '--metadata', `toc-title=${m.toc}`, '--metadata', `subtitle=${m.ver} ${version}`, '-o', join(DOCS, 'word', `${m.file}.docx`)]);

  // PDF via HTML
  const html = join(tmp, `${m.file}.html`);
  pandoc([src, '--from', 'markdown', '--to', 'html5', '--standalone', '--toc', '--toc-depth=2',
    '--resource-path', res, '--embed-resources', '--css', join(DOCS, 'tools', 'manual.css'),
    '--metadata', `toc-title=${m.toc}`, '--metadata', `subtitle=${m.ver} ${version}`,
    '-o', html]);
  const page = await browser.newPage();
  await page.goto(pathToFileURL(html).href, { waitUntil: 'load' });
  await page.pdf({
    path: join(DOCS, 'pdf', `${m.file}.pdf`),
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: `<div style="width:100%;font-size:8px;color:#8a90a0;padding:0 18mm;display:flex;justify-content:space-between;font-family:Arial,sans-serif">
      <span>PeopleDisplay ${version}</span><span>${m.page} <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`,
    margin: { top: '18mm', bottom: '20mm', left: '18mm', right: '18mm' },
  });
  await page.close();
  console.log(`✓ ${m.dir}/${m.file}: pdf + docx`);
}
await browser.close();
rmSync(tmp, { recursive: true, force: true });
writeFileSync(join(DOCS, 'pdf', '.gitattributes'), '*.pdf binary\n');
