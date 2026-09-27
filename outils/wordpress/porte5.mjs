// Porte 5 : l'éditeur prévisualise le site. Capture du canevas de l'éditeur et de la page publique
// à la même largeur, pour comparaison côte à côte.
import { session, editeur, WP } from './wp-session.mjs';
const [,, dossier = '.', ...slugs] = process.argv;
const { nav, page } = await session({ width: 1600, height: 1000 });
await page.goto(WP + '/wp-admin/edit.php?post_type=page');
const ids = await page.$$eval('#the-list tr', trs => trs.map(tr => ({ id: +tr.id.replace('post-', ''), slug: tr.querySelector('.post_name') ? tr.querySelector('.post_name').textContent : '' })));
for (const slug of slugs) {
  const id = ids.find(p => p.slug === slug).id;
  await editeur(page, `/wp-admin/post.php?post=${id}&action=edit`);
  await page.evaluate(() => { const d = wp.data.dispatch('core/edit-post'); if (wp.data.select('core/edit-post').isEditorSidebarOpened()) d.closeGeneralSidebar(); });
  await page.setViewportSize({ width: 1600, height: 12000 });
  await page.waitForTimeout(4000);
  const cadre = page.frameLocator('iframe[name="editor-canvas"]');
  const largeur = await page.$eval('iframe[name="editor-canvas"]', f => f.getBoundingClientRect().width);
  const racine = cadre.locator('.is-root-container');
  await racine.evaluate(async el => { for (const i of el.ownerDocument.images) i.loading = 'eager'; await new Promise(r => setTimeout(r, 1500)); });
  await racine.screenshot({ path: `${dossier}/editeur-${slug}.png` });
  const p = await nav.newPage({ viewport: { width: Math.round(largeur), height: 1000 } });
  await p.goto(`${WP}/${slug === 'accueil' ? '' : slug + '/'}`, { waitUntil: 'networkidle' });
  await p.addStyleTag({ content: '.entete,.wa-flottant{display:none!important} *{animation:none!important;transition:none!important}' });
  await p.locator('main').screenshot({ path: `${dossier}/public-${slug}.png` });
  await p.close();
  await page.setViewportSize({ width: 1600, height: 1000 });
  console.log(slug, 'largeur du canevas', Math.round(largeur));
}
await nav.close();
