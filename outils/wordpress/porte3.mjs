// Porte 3 : rendu et fonctionnement réels sur WordPress, en visiteur déconnecté.
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const WP = (process.env.WP_LOCAL || 'http://127.0.0.1:8088').replace(/\/$/, '');
const PAGES = ['', 'hematologie', 'depistage-neonatal-drepanocytose', 'anticoagulants-oraux-cinq-regles', 'hydroxyuree-avant-de-commencer', 'septembre-rouge-2026', 'therapie-genique-drepanocytose', 'don-de-sang', 'hemarthrose-enfant', 'journee-mondiale-drepanocytose', 'drepanocytose', 'hemophilie', 'cancers-du-sang', 'hemostase', 'diagnostic', 'outils', 'ebooks', 'rendez-vous', 'espace-patient', 'espace-pro', 'actualites', 'a-propos', 'contact'];
const nav = await chromium.launch();
let echecs = 0;
const ok = (nom, cond, detail = '') => { if (!cond) echecs++; console.log((cond ? 'OK   ' : 'ÉCHEC') + ' ' + nom + (detail ? ' — ' + detail : '')); };

// 1. Chaque page : images en 200, police Inter appliquée, pas de défilement horizontal à 390 px, aucune erreur JS
for (const slug of PAGES) {
  const ctx = await nav.newContext({ viewport: { width: 390, height: 844 } });
  const p = await ctx.newPage();
  const erreurs = [], ko = [];
  p.on('pageerror', e => erreurs.push(e.message));
  p.on('response', r => { if (r.status() >= 400 && !r.url().includes('favicon')) ko.push(r.status() + ' ' + r.url().replace(WP, '')); });
  await p.goto(`${WP}/${slug ? slug + '/' : ''}`, { waitUntil: 'networkidle' });
  const r = await p.evaluate(async () => {
    await document.fonts.ready;
    for (const i of document.images) i.loading = 'eager';
    window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 400)); window.scrollTo(0, 0);
    await Promise.all([...document.images].map(i => i.decode().catch(() => {})));
    const cassees = [...document.images].filter(i => i.getClientRects().length && !(i.complete && i.naturalWidth > 0)).map(i => i.src);
    return { debord: document.documentElement.scrollWidth - innerWidth, inter: document.fonts.check('16px Inter') && getComputedStyle(document.body).fontFamily.includes('Inter'), cassees };
  });
  ok(`/${slug} rendu`, r.debord <= 0 && r.inter && !r.cassees.length && !ko.length && !erreurs.length,
    [r.debord > 0 ? 'débord ' + r.debord + 'px' : '', r.inter ? '' : 'police', r.cassees.join(' '), ko.join(' '), erreurs.join(' ')].filter(Boolean).join(' · '));
  await ctx.close();
}

const ctx = await nav.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
const erreurs = [];
page.on('pageerror', e => erreurs.push(e.message));

// 2. Rendez-vous : parcours complet, demande enregistrée, créneau ensuite grisé
await page.goto(WP + '/rendez-vous/?type=hemophilie');
ok('type pré-sélectionné', (await page.locator('.type-consult.est-actif').innerText()).includes('Hémophilie'));
await page.click('.resa-suivant');
const jour = page.locator('.calendrier-grille [data-iso]').first();
const iso = await jour.getAttribute('data-iso');
await jour.click();
await page.click('.resa-suivant');
const creneau = await page.locator('.creneaux [data-valeur]:not(.est-pris)').first().getAttribute('data-valeur');
await page.click(`.creneaux [data-valeur="${creneau}"]`);
await page.click('.resa-suivant');
await page.fill('[name=prenom]', 'Test'); await page.fill('[name=nom]', 'Recette');
await page.fill('[name=tel]', '622 00 00 00'); await page.check('[name=consentement]');
await page.click('.resa-suivant');
await page.click('.resa-suivant');
await page.waitForSelector('.confirmation', { state: 'visible', timeout: 10000 }).catch(() => {});
const ref = await page.locator('.confirmation-num strong').innerText();
ok('demande de rendez-vous enregistrée', /^HG-\d{4}-\d{2}-\d{4}$/.test(ref), 'référence ' + ref);
await page.goto(WP + '/rendez-vous/');
await page.click('.type-consult >> nth=0'); await page.click('.resa-suivant');
await page.click(`.calendrier-grille [data-iso="${iso}"]`); await page.click('.resa-suivant');
ok('créneau demandé grisé pour cette date', await page.locator(`.creneaux [data-valeur="${creneau}"]`).evaluate(e => e.classList.contains('est-pris')), iso + ' ' + creneau);

// 3. Contact
await page.goto(WP + '/contact/');
await page.fill('[name=nom]', 'Test Recette'); await page.fill('[name=message]', 'Message de recette.');
await page.check('.contact-formulaire [name=consentement]');
await page.click('.contact-formulaire button[type=submit]');
await page.waitForLoadState('networkidle');
ok('message de contact envoyé', (await page.locator('.formulaire-retour').innerText().catch(() => '')).startsWith('Merci'));

// 4. Compte patient : création, carte « connecté », déconnexion, connexion par téléphone
const tel = '6' + String(Date.now()).slice(-8);
await page.goto(WP + '/espace-patient/');
await page.click('.onglets-mini .choix-item:has-text("Créer un compte")');
await page.fill('[name=prenom]', 'Aïssatou'); await page.fill('[name=nom]', 'Recette');
await page.fill('[name=tel]', tel); await page.fill('[name=mdp]', 'motdepasse-recette');
await page.check('form.formulaire-carte [name=consentement]');
await page.click('form.formulaire-carte.est-actif button[type=submit]');
await page.waitForLoadState('networkidle');
ok('compte patient créé et connecté', (await page.locator('.connexion-bonjour').innerText().catch(() => '')).includes('Aïssatou'));
await page.goto(WP + '/rendez-vous/');
await page.click('.type-consult >> nth=1'); await page.click('.resa-suivant');
await page.click('.calendrier-grille [data-iso] >> nth=2'); await page.click('.resa-suivant');
await page.locator('.creneaux [data-valeur]:not(.est-pris)').first().click(); await page.click('.resa-suivant');
await page.fill('[name=prenom]', 'Aïssatou'); await page.fill('[name=nom]', 'Recette');
await page.fill('[name=tel]', tel); await page.check('[name=consentement]');
await page.click('.resa-suivant'); await page.click('.resa-suivant');
await page.waitForSelector('.confirmation', { state: 'visible', timeout: 10000 }).catch(() => {});
await page.goto(WP + '/espace-patient/');
ok('la demande envoyée connecté apparaît dans son compte', await page.locator('.mes-demandes li').count() >= 1);
await page.click('.connexion-compte a:has-text("Se déconnecter")');
await page.waitForLoadState('networkidle');
await page.goto(WP + '/espace-patient/');
await page.fill('[name=log]', tel.replace(/(\d{3})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4'));
await page.fill('[name=pwd]', 'motdepasse-recette');
await page.click('form.formulaire-carte.est-actif button[type=submit]');
await page.waitForLoadState('networkidle');
ok('connexion avec le numéro tapé avec espaces', (await page.locator('.connexion-bonjour').innerText().catch(() => '')).includes('Aïssatou'));
ok('pas de barre d’administration pour un patient', await page.locator('#wpadminbar').count() === 0);
await page.goto(WP + '/wp-admin/');
ok('administration refusée au patient', !page.url().includes('/wp-admin'));
await ctx.clearCookies();

// 5. Accès professionnel
await page.goto(WP + '/espace-pro/');
await page.click('.onglets-mini .choix-item:has-text("Demander un accès")');
await page.fill('[name=ordre]', 'GN-0000'); await page.fill('[name=etablissement]', 'CHU de recette'); await page.fill('[name=mail]', 'recette@example.org');
await page.click('form.formulaire-carte.est-actif button[type=submit]');
await page.waitForLoadState('networkidle');
ok('demande d’accès pro reçue', (await page.locator('.formulaire-retour').innerText().catch(() => '')).startsWith('Demande reçue'));

// 6. Calculateurs et interactions
await page.goto(WP + '/outils/');
ok('Cockcroft-Gault 78', (await page.locator('[data-sortie=cg]').innerText()) === '78');
await page.fill('#i-blastes', '12');
ok('IPSS-R recalculé', (await page.locator('[data-sortie=risque]').innerText()) === 'Risque élevé');
await page.click('#orientation .v-macro');
ok('orientation d’une anémie', await page.locator('#orientation .combo-macro-areg').isVisible());
await page.goto(WP + '/hemostase/');
await page.click('.biblio-filtres .f-traitement');
ok('filtre bibliothèque', await page.locator('.biblio-grille .fiche-examen:not(.est-cache)').count() === 2);
await page.goto(WP + '/cancers-du-sang/');
await page.click('.onglet:has-text("Myélome")');
ok('onglets maladies', (await page.locator('.panneau-maladie.est-actif h2').innerText()).includes('plasmocytes'));
await page.goto(WP + '/espace-patient/#documents');
ok('ancre vers un onglet', (await page.locator('.tableau-contenu .variante.est-actif .tableau-titre').innerText()) === 'Mes documents');

// 6 bis. Actualités : les cartes mènent aux articles, chaque article propose trois lectures
await page.goto(WP + '/actualites/');
await page.click('.article-vedette .lien-etire a');
await page.waitForLoadState('networkidle');
ok('la une mène à son article', page.url().includes('/depistage-neonatal-drepanocytose/'));
ok('« À lire aussi » : trois articles', await page.locator('.article-suite .article').count() === 3);
await page.click('.article-suite .article >> nth=0 >> .lien-etire a');
await page.waitForLoadState('networkidle');
ok('« À lire aussi » mène à un autre article', (await page.locator('h1.titre-article').count()) === 1 && !page.url().includes('/depistage-neonatal-drepanocytose/'));
await page.goto(WP + '/hematologie/');
ok('page Hématologie : cinq fiches', await page.locator('.bento .carte-domaine').count() === 5);

// 7. Sécurité visible depuis l'extérieur
const rep = await page.request.get(WP + '/');
const h = rep.headers();
ok('en-têtes de sécurité', h['x-content-type-options'] === 'nosniff' && h['x-frame-options'] === 'SAMEORIGIN' && !!h['referrer-policy']);
ok('liste des comptes fermée (REST)', (await page.request.get(WP + '/wp-json/wp/v2/users')).status() === 404);
ok('demandes absentes de l’API REST', (await page.request.get(WP + '/wp-json/wp/v2/hg_demande')).status() === 404);
const auteur = await page.request.get(WP + '/?author=1', { maxRedirects: 0 });
ok('?author=1 ne révèle aucun identifiant', !String(auteur.headers()['location'] || '').includes('/author/'), auteur.status() + ' ' + (auteur.headers()['location'] || ''));
ok('plan de site sans les comptes', !(await (await page.request.get(WP + '/wp-sitemap.xml')).text()).includes('users') && !(await (await page.request.get(WP + '/wp-sitemap-users-1.xml')).text()).includes('/author/'));
ok('langue du document : français', (await (await page.request.get(WP + '/')).text()).includes('<html lang="fr-FR"'));

console.log('Erreurs JS :', erreurs.length ? erreurs : 'aucune');
console.log(echecs ? `PORTE 3 : ${echecs} échec(s)` : 'PORTE 3 : franchie');
await nav.close();
