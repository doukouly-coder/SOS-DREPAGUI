// Exporte les couvertures en JPEG dans site/assets/img/ebooks/.
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const SORTIE = path.resolve(import.meta.dirname, '../../site/assets/img/ebooks');
fs.mkdirSync(SORTIE, { recursive: true });
const nav = await chromium.launch();
const page = await nav.newPage({ viewport: { width: 1400, height: 1800 } });
await page.goto('file://' + path.resolve(import.meta.dirname, 'couvertures.html'));
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(800);
const noms = { c1: 'hemogramme-nfs', c2: 'drepanocytose-guide-familles', c3: 'hemostase-bilan-decision', c4: 'hemophilie-quotidien', c5: 'cancers-reperes', c6: 'transfusion-securite', c7: 'cas-cliniques', c8: 'urgences-hematologiques' };
for (const [id, nom] of Object.entries(noms)) {
  await page.locator('#' + id).screenshot({ path: path.join(SORTIE, nom + '.jpg'), type: 'jpeg', quality: 88 });
  console.log(nom + '.jpg');
}
await nav.close();
