#!/usr/bin/env node
// scripts/sync-sql.mjs
// install.sql (repository root) is the one schema file. The install wizard reads
// install/sql/install.sql, so that file is kept as an exact copy.
// Usage: npm run sync-sql

import { copyFileSync } from 'node:fs';
import { join } from 'node:path';
import { ROOT } from './lib/files.mjs';

copyFileSync(join(ROOT, 'install.sql'), join(ROOT, 'install', 'sql', 'install.sql'));
console.log('install/sql/install.sql bijgewerkt vanuit install.sql.');
