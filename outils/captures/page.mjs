// Capture une page de la maquette (bureau + mobile) et contrôle images / débordement.
// Usage : node page.mjs <fichier.html> <dossier-sortie>
import { createRequire } from 'module';
import path from 'path';
import fs from 'fs';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const [, , fichier = 'index.html', dossier = '.'] = process.argv;
const SORTIE = path.resolve(dossier); fs.mkdirSync(SORTIE, { recursive: true });
const nav = await chromium.launch();
for (const [nom, w, h, mobile] of [['bureau', 1440, 900, false], ['mobile', 390, 844, true]]) {
  const page = await nav.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: 1, isMobile: mobile, hasTouch: mobile });
  const erreurs = [];
  page.on('pageerror', e => erreurs.push(e.message));
  page.on('console', m => { if (m.type() === 'error') erreurs.push(m.text()); });
  await page.goto('http://127.0.0.1:4321/' + fichier, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '*,*::before,*::after{animation-play-state:paused!important;transition:none!important}html{scroll-behavior:auto!important}' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 40)); }
    window.scrollTo(0, 0);
    await Promise.race([Promise.all([...document.images].filter(i => i.offsetParent !== null).map(i => i.complete && i.naturalWidth ? 0 : new Promise(r => { i.onload = i.onerror = r; }))), new Promise(r => setTimeout(r, 4000))]);
  });
  await page.waitForTimeout(400);
  const bilan = await page.evaluate(() => ({
    h: document.documentElement.scrollHeight,
    debordX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    cassees: [...document.images].filter(i => i.offsetParent !== null && !(i.complete && i.naturalWidth > 0)).map(i => i.getAttribute('src')),
    trop_larges: [...document.querySelectorAll('body *')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > document.documentElement.clientWidth + 1 && !e.closest('.hero,.page-hero,[class*=calque],[class*=bulle],.categories,.sommaire ol,.pro,.patients,.rdv-carte,.septembre,.urgence,.carte-domaine,.onglets-liste'); }).slice(0, 5).map(e => e.tagName + '.' + e.className),
  }));
  console.log(fichier, nom, JSON.stringify(bilan), erreurs.length ? 'ERREURS ' + erreurs.join(' | ') : '');
  await page.screenshot({ path: path.join(SORTIE, fichier.replace('.html', '') + '-' + nom + '.png'), fullPage: true });
  await page.close();
}
await nav.close();
