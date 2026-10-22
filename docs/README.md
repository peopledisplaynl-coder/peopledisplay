# PeopleDisplay documentation

## Handleidingen (Nederlands)

| Handleiding | Voor | Lezen | PDF | Word |
|---|---|---|---|---|
| Gebruikershandleiding | Iedereen die PeopleDisplay dagelijks gebruikt | [online](nl/gebruikershandleiding.md) | [PDF](pdf/gebruikershandleiding.pdf) | [Word](word/gebruikershandleiding.docx) |
| Beheerdershandleiding | Beheerders van medewerkers, gebruikers en instellingen | [online](nl/beheerdershandleiding.md) | [PDF](pdf/beheerdershandleiding.pdf) | [Word](word/beheerdershandleiding.docx) |
| Installatiehandleiding | Installeren, cronjobs, bijwerken en verhuizen | [online](nl/installatiehandleiding.md) | [PDF](pdf/installatiehandleiding.pdf) | [Word](word/installatiehandleiding.docx) |

## Manuals (English)

| Manual | For | Read | PDF | Word |
|---|---|---|---|---|
| User Guide | Everyone who uses PeopleDisplay day to day | [online](en/user-guide.md) | [PDF](pdf/user-guide.pdf) | [Word](word/user-guide.docx) |
| Administrator Guide | Administrators of staff, users and settings | [online](en/admin-guide.md) | [PDF](pdf/admin-guide.pdf) | [Word](word/admin-guide.docx) |
| Installation Guide | Installing, cron jobs, updating and moving | [online](en/installation-guide.md) | [PDF](pdf/installation-guide.pdf) | [Word](word/installation-guide.docx) |

The PeopleDisplay interface is in Dutch; the English manuals show the Dutch labels with their meaning.

## For developers

- [ARCHITECTURE.md](ARCHITECTURE.md) — how the application is built.
- [../CLAUDE.md](../CLAUDE.md) — development rules, commands and the release flow.

### Rebuilding the manuals

The Markdown in `nl/` and `en/` is the source. The screenshots in `images/` are made by a
script from a demo installation with fictional data, so they can be redone after every
change to the interface.

```bash
# 1. Screenshots (Linux/macOS; PHP 8 CLI with pdo_mysql, a throw-away MariaDB/MySQL, Node 20+)
npm install
npx playwright-core install chromium          # once, unless PLAYWRIGHT_BROWSERS_PATH is set
DB_USER=… DB_PASS=… npm run docs:screenshots  # drops and recreates pd_demo and pd_install!

# 2. PDF and Word (needs pandoc)
npm run docs:build
```

| File | Purpose |
|---|---|
| `tools/demo-seed.php` | Fills a fresh database with fictional locations, staff, users, visitors |
| `tools/screenshots.mjs` | Takes every screenshot (Playwright); pass names to redo only some |
| `tools/make-screenshots.sh` | Runs everything: copies the app, seeds, starts PHP servers, takes the screenshots |
| `tools/build-docs.mjs` | Markdown → PDF (via HTML + `tools/manual.css`) and Word |
| `tools/image-sizes.lua` | Pandoc filter that sizes the small screenshots in PDF and Word |

Add a screenshot: add a `shot(...)` or `adminPage(...)` line in `tools/screenshots.mjs`, reference
`../images/<name>.png` in both languages, rerun both steps. `npm test` checks that every
referenced screenshot exists and that the Dutch and English manuals use the same ones.
