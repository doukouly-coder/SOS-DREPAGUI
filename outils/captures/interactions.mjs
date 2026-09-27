// Teste les interactions de la maquette dans Chromium et capture l'état final.
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const B = 'http://127.0.0.1:4321/';
const OUT = process.argv[2] || '.';
const nav = await chromium.launch();
const page = await nav.newPage({ viewport: { width: 1280, height: 900 } });
const erreurs = [];
page.on('pageerror', e => erreurs.push(e.message));
const ok = (nom, cond) => console.log((cond ? 'OK   ' : 'ÉCHEC') + ' ' + nom);

// 1. Rendez-vous : parcours complet
await page.goto(B + 'rendez-vous.html?type=hemophilie');
ok('type pré-sélectionné par ?type=', await page.locator('.type-consult.est-actif').innerText().then(t => t.includes('Hémophilie')));
await page.click('.resa-suivant');
await page.click('.calendrier-grille span:text-is("14")');
ok('récap date', (await page.locator('.recap [data-recap=date]').innerText()).includes('14 octobre'));
await page.click('.resa-suivant');
await page.click('.creneaux span:text-is("10:30")');
await page.click('.resa-suivant');
await page.click('.resa-suivant');
ok('validation des coordonnées', (await page.locator('.resa-aide').innerText()).length > 0);
await page.fill('[name=prenom]', 'Aïssatou'); await page.fill('[name=nom]', 'Camara');
await page.fill('[name=tel]', '622 00 00 00'); await page.check('[name=consentement]');
await page.click('.resa-suivant');
ok('étape 5 : récap patient', (await page.locator('.resa-verif [data-recap=patient]').innerText()) === 'Aïssatou Camara');
await page.click('.resa-suivant');
ok('confirmation affichée', await page.locator('.confirmation').isVisible());
await page.screenshot({ path: OUT + '/test-rdv-confirmation.png' });

// 2. eBooks : filtre Gratuits
await page.goto(B + 'ebooks.html');
await page.click('.boutique-filtres .f-gratuit');
ok('filtre eBooks gratuits = 3', await page.locator('.livres-boutique .livre:not(.est-cache)').count() === 3);

// 3. Cancers : onglet Myélome
await page.goto(B + 'cancers-du-sang.html');
await page.click('.onglet:has-text("Myélome")');
ok('onglet myélome', await page.locator('.panneau-maladie.est-actif h2').innerText().then(t => t.includes('plasmocytes')));

// 4. Hémostase : TP bas × TCA allongé
await page.goto(B + 'hemostase.html');
await page.click('.outil-carte .param:nth-of-type(1) .v-bas');
await page.click('.outil-carte .param:nth-of-type(2) .v-allonge');
ok('outil bilan bas-allongé', await page.locator('.outil-carte .combo-bas-allonge').isVisible());
ok('bouton pressé annoncé (aria-pressed)', (await page.locator('.outil-carte .v-bas').getAttribute('aria-pressed')) === 'true');
await page.click('.biblio-filtres .f-traitement');
ok('bibliothèque : 2 traitements', await page.locator('.biblio-grille .fiche-examen:not(.est-cache)').count() === 2);

// 5. Outils : calculs
await page.goto(B + 'outils.html');
ok('Cockcroft 78', (await page.locator('[data-sortie=cg]').innerText()) === '78');
ok('CKD-EPI 82', (await page.locator('[data-sortie=ckd]').innerText()) === '82');
ok('IPSS-R 3,5', (await page.locator('[data-sortie=score]').innerText()) === '3,5');
await page.fill('#i-blastes', '12');
ok('IPSS-R recalculé (5,5 → élevé)', (await page.locator('[data-sortie=risque]').innerText()) === 'Risque élevé');
await page.fill('#nfs-plq', '40'); await page.fill('#nfs-pnn', '0,4');
ok('NFS pancytopénie', (await page.locator('[data-sortie=titre]').innerText()) === 'Pancytopénie');
await page.click('#orientation .v-macro');
ok('orientation macro arégénérative', await page.locator('#orientation .combo-macro-areg').isVisible());

// 6. Espace patient : ancre #documents ouvre l'onglet
await page.goto(B + 'espace-patient.html#documents');
ok('onglet documents via ancre', await page.locator('.tableau-contenu .variante.est-actif .tableau-titre').innerText().then(t => t === 'Mes documents'));

// 7. Espace pro : quiz et filtres
await page.goto(B + 'espace-pro.html');
await page.click('.quiz-options .choix-item:has-text("Doppler")');
ok('quiz bonne réponse', await page.locator('.quiz-juste').isVisible());
await page.click('.facettes .f-urgence');
ok('ressources urgence = 4', await page.locator('.ressource:not(.est-cache)').count() === 4);

// 8. Drépanocytose : transmission AA × SS
await page.goto(B + 'drepanocytose.html');
await page.click('.choix-item:text-is("AA × SS")');
ok('transmission AA×SS', (await page.locator('.variante.est-actif .bilan-infographie').last().innerText()).includes('tous les enfants seront porteurs'));

// 8 bis. Actualités : filtre Campagnes ; accueil : recherche
await page.goto(B + 'actualites.html');
await page.click('.actus-filtres .f-campagnes');
ok('actualités campagnes = 3', await page.locator('.actus-liste .article:not(.est-cache)').count() === 3);
await page.goto(B + 'index.html');
ok('recherche de l’accueil = bloc core/search', await page.locator('form.wp-block-search input[name=s]').count() === 1);

// 9. Menu mobile
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(B + 'index.html');
await page.click('.burger'); await page.waitForTimeout(400);
ok('menu mobile ouvert', await page.locator('.panneau-menu.est-ouvert').isVisible());
await page.screenshot({ path: OUT + '/test-menu-mobile.png' });

// 10. Sous-menu Hématologie (bureau)
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(B + 'index.html');
await page.hover('.menu-deroulant > a');
await page.waitForTimeout(300);
ok('sous-menu visible au survol', await page.locator('.sous-menu').isVisible());
await page.screenshot({ path: OUT + '/test-sous-menu.png', clip: { x: 0, y: 0, width: 1440, height: 420 } });

console.log('Erreurs JS :', erreurs.length ? erreurs : 'aucune');
await nav.close();
