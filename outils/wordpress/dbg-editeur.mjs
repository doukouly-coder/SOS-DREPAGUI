import { session, editeur, WP } from './wp-session.mjs';
const { nav, page } = await session({ width: 1600, height: 1000 });
await page.goto(WP + '/wp-admin/edit.php?post_type=page');
const ids = await page.$$eval('#the-list tr', trs => trs.map(tr => ({ id: +tr.id.replace('post-', ''), slug: tr.querySelector('.post_name') ? tr.querySelector('.post_name').textContent : '' })));
await editeur(page, `/wp-admin/post.php?post=${ids.find(p => p.slug === 'accueil').id}&action=edit`);
const cadre = page.frames().find(f => f.name() === 'editor-canvas');
console.log(await cadre.evaluate(() => { const el = document.querySelector('.calque-milieu img'); const out = [];
  const parcourir = (regles, src) => { for (const r of regles) { if (r.cssRules && !r.selectorText) { parcourir(r.cssRules, src); continue; } if (!r.selectorText || !r.style) continue; let ok = false; try { ok = el.matches(r.selectorText); } catch (e) {} if (ok && [...r.style].some(x => /^(width|max-width|min-width)$/.test(x))) out.push(src.padEnd(20) + ' ' + r.selectorText.slice(0, 110) + ' { ' + [...r.style].filter(x => /width/.test(x)).map(x => x + ':' + r.style.getPropertyValue(x) + (r.style.getPropertyPriority(x) ? '!' : '')).join('; ') + ' }'); } };
  for (const s of document.styleSheets) { try { parcourir(s.cssRules, (s.href || s.ownerNode.id || 'inline').split('/').pop().slice(0, 20)); } catch (e) {} }
  out.push('attrs: ' + [...el.attributes].map(a => a.name + '=' + a.value.slice(0, 40)).join(' ')); return out.join('\n'); }));
console.log(await cadre.evaluate(() => [...document.querySelectorAll('.calque, .bulle, .hero-scene')].map(e => {
  const c = getComputedStyle(e), r = e.getBoundingClientRect(), img = e.querySelector('img');
  return `${e.className.split(' ').filter(x => !x.startsWith('block-editor') && x !== 'wp-block').join('.')} pos=${c.position} w=${Math.round(r.width)} h=${Math.round(r.height)} maxw=${c.maxWidth} ml=${c.marginLeft} | img w=${img ? Math.round(img.getBoundingClientRect().width) : '-'} style=${img ? img.getAttribute('style') : ''} parent=${img ? img.parentElement.className.slice(0, 50) : ''}`;
}).join('\n')));
await nav.close();
