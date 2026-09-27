// Exporte les scènes 3D en PNG transparents dans site/assets/img/.
// Usage : node rendre.mjs [scene ...]   (le serveur serve.js doit servir ce dossier)
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';

const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const BASE = process.env.BASE || 'http://127.0.0.1:4330';
const SORTIE = path.resolve(import.meta.dirname, 'png');

const SCENES = {
  'hero-arriere': [1400, 1400],
  'hero-milieu': [1600, 1600],
  'drepanocytose': [900, 900],
  'hemophilie': [900, 900],
  'cancers': [900, 900],
  'hemostase': [900, 900],
  'diagnostic': [900, 900],
  'globule-seul': [800, 800],
  'trio': [1000, 1000],
  'globule-profil': [700, 700],
};

const choix = process.argv.slice(2);
fs.mkdirSync(SORTIE, { recursive: true });
const navigateur = await chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });
const page = await navigateur.newPage();
page.on('console', m => { if (m.type() === 'error') console.error('  console:', m.text()); });
page.on('pageerror', e => console.error('  erreur:', e.message));

for (const [nom, [w, h]] of Object.entries(SCENES)) {
  if (choix.length && !choix.includes(nom)) continue;
  const t0 = Date.now();
  await page.goto(`${BASE}/scene.html?scene=${nom}&w=${w}&h=${h}`);
  await page.waitForFunction(() => window.__fini === true, null, { timeout: 180000 });
  const png = await page.evaluate(() => window.__png);
  fs.writeFileSync(path.join(SORTIE, `${nom}.png`), Buffer.from(png.split(',')[1], 'base64'));
  console.log(`${nom}.png  ${w}x${h}  ${((Date.now() - t0) / 1000).toFixed(1)} s`);
}
await navigateur.close();
