/* HEMATO GUI — interactions de la maquette.
   Règle 7 : toutes les variantes sont présentes dans la page, le script ne
   fait que basculer des classes. Sélection par classe, jamais par id. */
(function () {
  var entete = document.querySelector('.entete');
  var burger = document.querySelector('.burger');
  var panneau = document.querySelector('.panneau-menu');

  // Rend un élément non natif activable au clavier comme un bouton
  function commeBouton(el, action) {
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.addEventListener('click', action);
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); action(); }
    });
  }
  function enfantsDirects(conteneur, selecteur) {
    return [].filter.call(conteneur.querySelectorAll(selecteur), function (el) {
      return el.closest('.bascule, .outil-2d, .choix-exclusif, .filtrable, [data-outil-bilan], [data-groupe], [data-filtres]') === conteneur;
    });
  }

  // En-tête : filet dès qu'on a défilé
  if (entete) {
    var surDefilement = function () { entete.classList.toggle('est-defile', window.scrollY > 8); };
    window.addEventListener('scroll', surDefilement, { passive: true });
    surDefilement();
  }

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

  // Bascule générique : le n-ième déclencheur active la n-ième variante
  document.querySelectorAll('.bascule').forEach(function (bloc) {
    var declencheurs = enfantsDirects(bloc, '.choix-item, .onglet');
    var variantes = enfantsDirects(bloc, '.variante');
    declencheurs.forEach(function (d, i) {
      d.setAttribute('aria-pressed', String(d.classList.contains('est-actif')));
      commeBouton(d, function () {
        declencheurs.forEach(function (x) { x.classList.remove('est-actif'); x.setAttribute('aria-pressed', 'false'); });
        variantes.forEach(function (v) { v.classList.remove('est-actif'); });
        d.classList.add('est-actif');
        d.setAttribute('aria-pressed', 'true');
        if (variantes[i]) variantes[i].classList.add('est-actif');
      });
    });
  });

  // Choix exclusifs sans variante (maquette du téléphone, sélecteurs)
  document.querySelectorAll('.choix-exclusif, [data-groupe]').forEach(function (groupe) {
    var options = [].filter.call(groupe.children, function (o) { return !o.classList.contains('est-pris'); });
    options.forEach(function (opt) {
      commeBouton(opt, function () {
        options.forEach(function (o) { o.classList.remove('est-actif'); o.setAttribute('aria-pressed', 'false'); });
        opt.classList.add('est-actif');
        opt.setAttribute('aria-pressed', 'true');
        groupe.dispatchEvent(new CustomEvent('choix', { detail: opt }));
      });
    });
  });

  // Sélection multiple (filtres de l'accueil)
  document.querySelectorAll('.choix-multiple > *').forEach(function (f) {
    f.setAttribute('aria-pressed', String(f.classList.contains('est-actif')));
    commeBouton(f, function () {
      f.setAttribute('aria-pressed', String(f.classList.toggle('est-actif')));
    });
  });

  // Filtres d'une grille : data-filtre sur les boutons, data-type sur les éléments
  document.querySelectorAll('[data-filtres]').forEach(function (zone) {
    var cible = document.querySelector(zone.getAttribute('data-filtres'));
    if (!cible) return;
    var boutons = enfantsDirects(zone, '[data-filtre]');
    boutons.forEach(function (b) {
      commeBouton(b, function () {
        boutons.forEach(function (x) { x.classList.remove('est-actif'); });
        b.classList.add('est-actif');
        var f = b.getAttribute('data-filtre');
        cible.querySelectorAll('[data-type]').forEach(function (el) {
          var types = el.getAttribute('data-type').split(' ');
          el.classList.toggle('est-cache', f !== 'tous' && types.indexOf(f) === -1);
        });
      });
    });
  });

  // Filtres par classes : dans un .filtrable, un déclencheur « f-x » garde les éléments « t-x »
  document.querySelectorAll('.filtrable').forEach(function (zone) {
    var cible = zone.querySelector('.filtres-cible');
    var boutons = [].filter.call(zone.querySelectorAll('[class*="f-"]'), function (b) {
      return /(^|\s)f-[\w-]+/.test(b.className) && !cible.contains(b);
    });
    boutons.forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.classList.contains('est-actif')));
      commeBouton(b, function () {
        boutons.forEach(function (x) { x.classList.remove('est-actif'); x.setAttribute('aria-pressed', 'false'); });
        b.classList.add('est-actif');
        b.setAttribute('aria-pressed', 'true');
        var f = (b.className.match(/(?:^|\s)f-([\w-]+)/) || [])[1];
        [].forEach.call(cible.children, function (el) {
          el.classList.toggle('est-cache', f !== 'tous' && !el.classList.contains('t-' + f));
        });
      });
    });
  });

  // Outil à deux entrées, par classes : .param > .choix-item.v-x ; résultats .variante.combo-x-y
  document.querySelectorAll('.outil-2d').forEach(function (outil) {
    var groupes = outil.querySelectorAll('.param');
    function valeur(el) { return (el.className.match(/(?:^|\s)v-([\w-]+)/) || [])[1] || ''; }
    function actualiser() {
      var combo = [].map.call(groupes, function (g) { var a = g.querySelector('.choix-item.est-actif'); return a ? valeur(a) : ''; }).join('-');
      outil.querySelectorAll('.variante').forEach(function (v) { v.classList.toggle('est-actif', v.classList.contains('combo-' + combo)); });
    }
    groupes.forEach(function (g) {
      var titre = g.querySelector('.param-titre, .label');
      g.setAttribute('role', 'group');
      if (titre) g.setAttribute('aria-label', titre.textContent);
      var items = g.querySelectorAll('.choix-item');
      items.forEach(function (it) {
        it.setAttribute('aria-pressed', String(it.classList.contains('est-actif')));
        commeBouton(it, function () {
          items.forEach(function (x) { x.classList.remove('est-actif'); x.setAttribute('aria-pressed', 'false'); });
          it.classList.add('est-actif');
          it.setAttribute('aria-pressed', 'true');
          actualiser();
        });
      });
    });
    actualiser();
  });

  // Outil à deux entrées (bilan d'hémostase) : la combinaison choisit la variante
  document.querySelectorAll('[data-outil-bilan]').forEach(function (outil) {
    var groupes = outil.querySelectorAll('[data-param]');
    function actualiser() {
      var combo = [].map.call(groupes, function (g) {
        var actif = g.querySelector('.choix-item.est-actif');
        return actif ? actif.getAttribute('data-valeur') : '';
      }).join('-');
      outil.querySelectorAll('[data-combo]').forEach(function (v) {
        v.classList.toggle('est-actif', v.getAttribute('data-combo') === combo);
      });
    }
    groupes.forEach(function (g) {
      var items = g.querySelectorAll('.choix-item');
      items.forEach(function (it) {
        commeBouton(it, function () {
          items.forEach(function (x) { x.classList.remove('est-actif'); });
          it.classList.add('est-actif');
          actualiser();
        });
      });
    });
    actualiser();
  });

  // Sommaire : met en évidence la partie lue
  var liens = document.querySelectorAll('.sommaire a[href^="#"]');
  if (liens.length && 'IntersectionObserver' in window) {
    var parId = {};
    liens.forEach(function (a) { parId[a.getAttribute('href').slice(1)] = a; });
    var obs = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) {
        if (!e.isIntersecting) return;
        liens.forEach(function (a) { a.classList.remove('est-actif'); });
        var a = parId[e.target.id];
        if (a) {
          a.classList.add('est-actif');
          var liste = a.closest('ol');
          if (liste && liste.scrollWidth > liste.clientWidth) {
            liste.scrollTo({ left: a.offsetLeft - 20, behavior: 'smooth' });
          }
        }
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    Object.keys(parId).forEach(function (id) { var s = document.getElementById(id); if (s) obs.observe(s); });
  }

  // Une ancre qui vise un onglet (#documents) active cet onglet
  function ouvrirAncre() {
    var id = decodeURIComponent(location.hash.slice(1));
    var cible = id && document.getElementById(id);
    if (cible && cible.matches('.choix-item, .onglet') && !cible.classList.contains('est-actif')) cible.click();
  }
  window.addEventListener('hashchange', ouvrirAncre);
  ouvrirAncre();

  window.HG = { commeBouton: commeBouton };
})();
