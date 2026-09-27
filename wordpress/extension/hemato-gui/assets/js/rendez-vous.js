/* HEMATO GUI — parcours de prise de rendez-vous.
   Toutes les étapes sont dans la page ; le script bascule des classes et
   recopie les choix dans le récapitulatif. Sur WordPress, l'extension pose
   data-envoi (adresse admin-ajax) et data-jeton (nonce) : la demande est alors
   réellement envoyée et la référence renvoyée par le serveur est affichée.
   Sans ces attributs (maquette), la confirmation reste locale. */
(function () {
  var resa = document.querySelector('[data-reservation]');
  if (!resa) return;
  var etapes = resa.querySelectorAll('.etape-resa');
  var pastilles = resa.querySelectorAll('.resa-etapes li');
  var barre = resa.querySelector('.resa-progression i');
  var retour = resa.querySelector('.resa-retour');
  var suivant = resa.querySelector('.resa-suivant');
  var aide = resa.querySelector('.resa-aide');
  var courant = 0, confirme = false;

  function choix(groupe, attribut) {
    var actif = resa.querySelector('[data-groupe="' + groupe + '"] .est-actif');
    return actif ? (actif.getAttribute(attribut || 'data-valeur') || '') : '';
  }
  function champ(nom) { var el = resa.querySelector('[name="' + nom + '"]'); return el ? el.value.trim() : ''; }
  function recap() {
    var patient = [champ('prenom'), champ('nom')].filter(Boolean).join(' ');
    var valeurs = { type: choix('type') || 'À choisir', date: choix('date') || '—', heure: choix('heure') || '—', patient: patient || '—' };
    resa.querySelectorAll('[data-recap]').forEach(function (dd) { dd.textContent = valeurs[dd.getAttribute('data-recap')]; });
  }
  function valide(i) {
    if (i === 0 && !choix('type')) return 'Choisissez un type de consultation.';
    if (i === 1 && !choix('date')) return 'Choisissez une date disponible.';
    if (i === 2 && !choix('heure')) return 'Choisissez un horaire.';
    if (i === 3) {
      if (!champ('prenom') || !champ('nom')) return 'Indiquez le prénom et le nom du patient.';
      if (champ('tel').replace(/\D/g, '').length < 9) return 'Indiquez un numéro de téléphone à 9 chiffres.';
      if (!resa.querySelector('[name="consentement"]').checked) return 'Merci d’accepter l’utilisation de vos données pour ce rendez-vous.';
    }
    return '';
  }
  function afficher(i) {
    courant = i;
    etapes.forEach(function (e, k) { e.classList.toggle('est-actif', k === i); });
    pastilles.forEach(function (p, k) { p.classList.toggle('est-actif', k === i); p.classList.toggle('est-fait', k < i); });
    barre.style.width = ((i + 1) / etapes.length * 100) + '%';
    retour.hidden = i === 0 || confirme;
    suivant.firstChild.textContent = i === etapes.length - 1 ? 'Envoyer la demande' : 'Continuer';
    suivant.style.display = confirme ? 'none' : '';
    aide.textContent = '';
    recap();
  }
  function confirmer(reference) {
    confirme = true;
    var ref = resa.querySelector('.confirmation-num strong');
    if (ref && reference) ref.textContent = reference;
    etapes[courant].classList.add('est-confirme');
    pastilles.forEach(function (p) { p.classList.add('est-fait'); });
    afficher(courant);
  }
  function envoyer() {
    var echec = 'L’envoi n’a pas abouti. Réessayez, ou écrivez-nous sur WhatsApp au +224 628 73 31 43.';
    var donnees = new FormData();
    donnees.append('action', 'hg_rendez_vous');
    donnees.append('jeton', resa.getAttribute('data-jeton') || '');
    donnees.append('type', choix('type', 'data-slug'));
    donnees.append('date', choix('date', 'data-iso'));
    donnees.append('heure', choix('heure'));
    ['prenom', 'nom', 'tel', 'mail', 'age', 'motif', 'site_web'].forEach(function (n) { donnees.append(n, champ(n)); });
    var premiere = resa.querySelector('[name="premiere"]:checked');
    donnees.append('premiere', premiere ? premiere.value : '');
    donnees.append('consentement', resa.querySelector('[name="consentement"]').checked ? '1' : '');
    suivant.disabled = true;
    aide.textContent = 'Envoi en cours…';
    fetch(resa.getAttribute('data-envoi'), { method: 'POST', body: donnees, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (rep) {
        suivant.disabled = false;
        if (rep && rep.success) { confirmer(rep.data.reference); return; }
        aide.textContent = (rep && rep.data && rep.data.message) || echec;
      })
      .catch(function () { suivant.disabled = false; aide.textContent = echec; });
  }
  suivant.addEventListener('click', function () {
    var erreur = valide(courant);
    if (erreur) { aide.textContent = erreur; return; }
    if (courant < etapes.length - 1) { afficher(courant + 1); resa.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    if (resa.getAttribute('data-envoi')) { envoyer(); return; }
    confirmer();
  });
  retour.addEventListener('click', function () { if (courant > 0) afficher(courant - 1); });
  pastilles.forEach(function (p, k) {
    p.addEventListener('click', function () { if (k < courant && !confirme) afficher(k); });
  });
  resa.addEventListener('input', recap);
  resa.querySelectorAll('[data-groupe]').forEach(function (g) { g.addEventListener('choix', recap); });

  // Créneaux déjà demandés pour la date choisie (fournis par l'extension : data-pris sur .creneaux)
  var creneaux = resa.querySelector('.creneaux[data-pris]');
  var grille = resa.querySelector('[data-groupe="date"]');
  if (creneaux && grille) {
    var pris = {};
    try { pris = JSON.parse(creneaux.getAttribute('data-pris')) || {}; } catch (e) { pris = {}; }
    grille.addEventListener('choix', function () {
      var occupes = pris[choix('date', 'data-iso')] || [];
      [].forEach.call(creneaux.querySelectorAll('[data-valeur]'), function (c) {
        var occupe = occupes.indexOf(c.textContent.trim()) !== -1;
        c.classList.toggle('est-pris', occupe);
        c.setAttribute('aria-disabled', String(occupe));
        if (occupe && c.classList.contains('est-actif')) { c.classList.remove('est-actif'); c.setAttribute('aria-pressed', 'false'); }
      });
      recap();
    });
  }

  // Type pré-sélectionné depuis un lien : rendez-vous.html?type=drepanocytose
  var type = new URLSearchParams(location.search).get('type');
  var carte = type && resa.querySelector('.type-consult[data-slug="' + type + '"]');
  if (carte) carte.click();
  afficher(0);
})();
