// Porte 2 : chaque page s'ouvre dans l'éditeur sans bloc invalide, images liées à la médiathèque.
import { session, editeur } from './wp-session.mjs';
const { nav, page } = await session();
let pages = [];
for (const type of ['page', 'post']) {
  await page.goto((process.env.WP_LOCAL || 'http://127.0.0.1:8088') + '/wp-admin/edit.php?post_type=' + type + '&post_status=publish');
  pages = pages.concat(await page.$$eval('#the-list tr', trs => trs.map(tr => ({ id: +tr.id.replace('post-', ''), slug: tr.querySelector('.row-title').textContent.trim() }))));
}
let echecs = 0;
for (const { id, slug } of pages) {
  await editeur(page, `/wp-admin/post.php?post=${id}&action=edit`);
  const r = await page.evaluate(() => {
    const aplatir = bs => bs.reduce((a, b) => a.concat([b], aplatir(b.innerBlocks || [])), []);
    const tous = aplatir(wp.data.select('core/block-editor').getBlocks());
    const invalides = tous.filter(b => b.isValid === false).map(b => b.name + ' ' + (b.attributes.className || ''));
    const images = tous.filter(b => b.name === 'core/image');
    return { total: tous.length, invalides, images: images.length, liees: images.filter(b => b.attributes.id).length };
  });
  const ok = r.invalides.length === 0 && r.images === r.liees;
  if (!ok) echecs++;
  console.log((ok ? 'OK   ' : 'ÉCHEC') + ` ${String(slug).padEnd(16)} ${r.total} blocs · invalides ${r.invalides.length} · images liées ${r.liees}/${r.images}` + (r.invalides.length ? '\n      ' + r.invalides.slice(0, 5).join('\n      ') : ''));
}
console.log(echecs ? `PORTE 2 : ${echecs} page(s) en échec` : 'PORTE 2 : franchie');
await nav.close();
