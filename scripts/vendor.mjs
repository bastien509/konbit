// Copies the Bryntum Scheduler build from node_modules into assets/vendor/bryntum.
// Run after `npm install`:  npm run vendor
import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const src = join('node_modules', '@bryntum', 'scheduler');
const dest = join('assets', 'vendor', 'bryntum');
if (!existsSync(src)) {
  console.error('Run `npm install` first.');
  process.exit(1);
}
mkdirSync(dest, { recursive: true });
for (const f of ['scheduler.umd.js', 'scheduler.css', 'svalbard-light.css', 'svalbard-dark.css']) {
  cpSync(join(src, f), join(dest, f));
}
cpSync(join(src, 'fontawesome'), join(dest, 'fontawesome'), { recursive: true });
console.log('Bryntum Scheduler copied to ' + dest);
