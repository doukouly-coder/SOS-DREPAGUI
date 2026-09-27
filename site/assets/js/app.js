/* HEMATO GUI — interactions de la maquette.
   Le script ne fait que basculer des classes : aucun contenu n'est injecté. */
(function () {
  var entete = document.querySelector('.entete');
  var burger = document.querySelector('.burger');
  var panneau = document.querySelector('.panneau-menu');

  // En-tête : filet dès qu'on a défilé
  function surDefilement() { entete.classList.toggle('est-defile', window.scrollY > 8); }
  window.addEventListener('scroll', surDefilement, { passive: true });
  surDefilement();

  // Menu mobile
  function basculerMenu(ouvrir) {
    var etat = typeof ouvrir === 'boolean' ? ouvrir : burger.getAttribute('aria-expanded') !== 'true';
    burger.setAttribute('aria-expanded', String(etat));
    burger.setAttribute('aria-label', etat ? 'Fermer le menu' : 'Ouvrir le menu');
    panneau.classList.toggle('est-ouvert', etat);
    document.documentElement.style.overflow = etat ? 'hidden' : '';
  }
  if (burger && panneau) {
    burger.addEventListener('click', function () { basculerMenu(); });
    panneau.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { basculerMenu(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') basculerMenu(false); });
  }

  // Choix exclusifs (maquette du téléphone) : une seule option active par groupe
  document.querySelectorAll('[data-groupe]').forEach(function (groupe) {
    var options = groupe.querySelectorAll(':scope > span:not(.est-pris)');
    options.forEach(function (opt) {
      opt.setAttribute('role', 'button');
      opt.setAttribute('tabindex', '0');
      function activer() {
        options.forEach(function (o) { o.classList.remove('est-actif'); o.setAttribute('aria-pressed', 'false'); });
        opt.classList.add('est-actif');
        opt.setAttribute('aria-pressed', 'true');
      }
      opt.addEventListener('click', activer);
      opt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activer(); }
      });
    });
  });

  // Filtres de la bibliothèque pro : sélection multiple
  document.querySelectorAll('.filtre').forEach(function (f) {
    f.setAttribute('role', 'button');
    f.setAttribute('tabindex', '0');
    f.setAttribute('aria-pressed', String(f.classList.contains('est-actif')));
    function basculer() {
      var actif = f.classList.toggle('est-actif');
      f.setAttribute('aria-pressed', String(actif));
    }
    f.addEventListener('click', basculer);
    f.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); basculer(); }
    });
  });
})();
