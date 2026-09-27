// Captures pleine page de la maquette (bureau 1440 px, mobile 390 px) + contrôle des images.
// Usage : node capturer.mjs [url] [dossier-de-sortie]
import { createRequire } from 'module';
import path from 'path';
import fs from 'fs';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const URL = process.argv[2] || 'http://127.0.0.1:4321/';
const SORTIE = path.resolve(process.argv[3] || '.');
fs.mkdirSync(SORTIE, { recursive: true });
const nav = await chromium.launch();
for (const [nom, w, h, mobile] of [['bureau', 1440, 900, false], ['mobile', 390, 844, true]]) {
  const page = await nav.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: mobile ? 2 : 1, isMobile: mobile, hasTouch: mobile });
  await page.goto(URL, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '*,*::before,*::after{animation-play-state:paused!important;transition:none!important}html{scroll-behavior:auto!important}' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); }
    window.scrollTo(0, 0);
    await Promise.all([...document.images].map(i => i.complete && i.naturalWidth ? 0 : new Promise(r => { i.onload = i.onerror = r; })));
  });
  await page.waitForTimeout(600);
  const bilan = await page.evaluate(() => ({
    hauteur: document.documentElement.scrollHeight,
    debordementX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    imagesCassees: [...document.images].filter(i => !(i.complete && i.naturalWidth > 0)).map(i => i.getAttribute('src')),
    police: getComputedStyle(document.body).fontFamily,
    policeChargee: document.fonts.check('16px Inter'),
  }));
  console.log(nom, JSON.stringify(bilan));
  await page.screenshot({ path: path.join(SORTIE, `accueil-${nom}.png`), fullPage: true });
  await page.close();
}
await nav.close();
