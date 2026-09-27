/* HEMATO GUI — parcours de prise de rendez-vous (maquette).
   Toutes les étapes sont dans la page ; le script bascule des classes et
   recopie les choix dans le récapitulatif. En production, ce parcours est
   assuré par l'extension de réservation. */
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

  function choix(groupe) {
    var actif = resa.querySelector('[data-groupe="' + groupe + '"] .est-actif');
    return actif ? actif.getAttribute('data-valeur') : '';
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
  suivant.addEventListener('click', function () {
    var erreur = valide(courant);
    if (erreur) { aide.textContent = erreur; return; }
    if (courant < etapes.length - 1) { afficher(courant + 1); resa.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    confirme = true;
    etapes[courant].classList.add('est-confirme');
    pastilles.forEach(function (p) { p.classList.add('est-fait'); });
    afficher(courant);
  });
  retour.addEventListener('click', function () { if (courant > 0) afficher(courant - 1); });
  pastilles.forEach(function (p, k) {
    p.addEventListener('click', function () { if (k < courant && !confirme) afficher(k); });
  });
  resa.addEventListener('input', recap);
  resa.querySelectorAll('[data-groupe]').forEach(function (g) { g.addEventListener('choix', recap); });

  // Type pré-sélectionné depuis un lien : rendez-vous.html?type=drepanocytose
  var type = new URLSearchParams(location.search).get('type');
  var carte = type && resa.querySelector('.type-consult[data-slug="' + type + '"]');
  if (carte) carte.click();
  afficher(0);
})();
