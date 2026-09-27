/* HEMATO GUI — graphiques du tableau de bord administrateur (maquette).
   Une série par graphique, donc une seule couleur et pas de légende ; marques
   fines (ligne 2 px, barres ≤ 24 px, bout arrondi 4 px), grille en filet,
   info-bulle au survol et au clavier, et un tableau des données sous chaque graphique. */
(function () {
  var NS = 'http://www.w3.org/2000/svg';
  var C = { rouge: '#C8102E', encre: '#1D1D1F', graphite: '#6E6E73', grille: '#E4E4E9', attenue: '#C7C7CD', surface: '#FFFFFF' };
  var bulle = document.querySelector('.infobulle');
  var fr = function (n) { return n.toLocaleString('fr-FR'); };

  function el(nom, attrs, parent) {
    var e = document.createElementNS(NS, nom);
    Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
    if (parent) parent.appendChild(e);
    return e;
  }
  function texte(parent, x, y, contenu, attrs) {
    var t = el('text', Object.assign({ x: x, y: y, 'font-size': 12, fill: C.graphite }, attrs || {}), parent);
    t.textContent = contenu;
    return t;
  }
  function pasPropre(max, n) {
    var brut = max / n, p = Math.pow(10, Math.floor(Math.log10(brut))), r = brut / p;
    return (r <= 1 ? 1 : r <= 2 ? 2 : r <= 2.5 ? 2.5 : r <= 5 ? 5 : 10) * p;
  }
  // Barre arrondie (4 px) au bout, carrée à la ligne de base
  function barre(x, y, l, h, sens) {
    var r = Math.min(4, sens === 'h' ? l : h, sens === 'h' ? h / 2 : l / 2);
    if (sens === 'h') return 'M' + x + ',' + y + 'H' + (x + l - r) + 'Q' + (x + l) + ',' + y + ' ' + (x + l) + ',' + (y + r) + 'V' + (y + h - r) + 'Q' + (x + l) + ',' + (y + h) + ' ' + (x + l - r) + ',' + (y + h) + 'H' + x + 'Z';
    return 'M' + x + ',' + (y + h) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y + 'H' + (x + l - r) + 'Q' + (x + l) + ',' + y + ' ' + (x + l) + ',' + (y + r) + 'V' + (y + h) + 'Z';
  }
  function montrer(evt, valeur, etiquette, unite) {
    bulle.textContent = '';
    var v = document.createElement('strong'); v.textContent = fr(valeur) + ' ' + unite;
    var e = document.createElement('span'); e.textContent = etiquette;
    bulle.appendChild(v); bulle.appendChild(e);
    bulle.hidden = false;
    var x = evt.clientX, y = evt.clientY;
    if (evt.type === 'focus' || x === undefined) { var r = evt.target.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top; }
    var w = bulle.offsetWidth;
    bulle.style.left = Math.min(window.innerWidth - w - 12, Math.max(12, x - w / 2)) + 'px';
    bulle.style.top = (y - bulle.offsetHeight - 14) + 'px';
  }
  function cacher() { bulle.hidden = true; }

  function tableau(conteneur, etiquettes, valeurs, unite) {
    var d = document.createElement('details'); d.className = 'graphe-table';
    var s = document.createElement('summary'); s.textContent = 'Voir les données'; d.appendChild(s);
    var t = document.createElement('table'), tb = document.createElement('tbody');
    etiquettes.forEach(function (e, i) {
      var tr = document.createElement('tr'), a = document.createElement('td'), b = document.createElement('td');
      a.textContent = e; b.textContent = fr(valeurs[i]) + ' ' + unite; tr.appendChild(a); tr.appendChild(b); tb.appendChild(tr);
    });
    t.appendChild(tb); d.appendChild(t); conteneur.after(d);
  }

  var DESSINS = {
    ligne: function (box, E, V, unite) {
      var W = box.clientWidth, H = 260, m = { g: 40, d: 52, h: 14, b: 30 };
      var max = Math.max.apply(null, V), pas = pasPropre(max, 3), haut = Math.ceil(max / pas) * pas;
      var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img', tabindex: 0, 'aria-label': 'Visites par jour sur 30 jours ; flèches gauche et droite pour parcourir' });
      var X = function (i) { return m.g + i * (W - m.g - m.d) / (V.length - 1); };
      var Y = function (v) { return m.h + (1 - v / haut) * (H - m.h - m.b); };
      for (var t = 0; t <= haut; t += pas) {
        el('line', { x1: m.g, x2: W - m.d, y1: Y(t), y2: Y(t), stroke: C.grille, 'stroke-width': 1 }, svg);
        texte(svg, m.g - 8, Y(t) + 4, fr(t), { 'text-anchor': 'end', 'font-variant-numeric': 'tabular-nums' });
      }
      (W < 560 ? [0, 14, 29] : [0, 7, 14, 21, 29]).forEach(function (i) { texte(svg, X(i), H - 8, E[i], { 'text-anchor': i === 0 ? 'start' : i === 29 ? 'end' : 'middle' }); });
      var d = V.map(function (v, i) { return (i ? 'L' : 'M') + X(i).toFixed(1) + ',' + Y(v).toFixed(1); }).join('');
      el('path', { d: d + 'L' + X(V.length - 1) + ',' + Y(0) + 'L' + X(0) + ',' + Y(0) + 'Z', fill: C.rouge, 'fill-opacity': .1 }, svg);
      el('path', { d: d, fill: 'none', stroke: C.rouge, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, svg);
      var n = V.length - 1;
      el('circle', { cx: X(n), cy: Y(V[n]), r: 4, fill: C.rouge, stroke: C.surface, 'stroke-width': 2 }, svg);
      texte(svg, X(n) + 10, Y(V[n]) + 4, fr(V[n]), { fill: C.encre, 'font-weight': 650, 'font-size': 13 });
      var repere = el('line', { y1: m.h, y2: H - m.b, stroke: C.encre, 'stroke-opacity': .35, 'stroke-width': 1, visibility: 'hidden' }, svg);
      var point = el('circle', { r: 5, fill: C.rouge, stroke: C.surface, 'stroke-width': 2, visibility: 'hidden' }, svg);
      var zone = el('rect', { x: m.g, y: 0, width: W - m.g - m.d, height: H, fill: 'transparent' }, svg);
      var courant = n;
      function viser(i, evt) {
        courant = i;
        repere.setAttribute('x1', X(i)); repere.setAttribute('x2', X(i)); repere.setAttribute('visibility', 'visible');
        point.setAttribute('cx', X(i)); point.setAttribute('cy', Y(V[i])); point.setAttribute('visibility', 'visible');
        var r = svg.getBoundingClientRect();
        montrer(evt && evt.clientX !== undefined ? evt : { clientX: r.left + X(i), clientY: r.top + Y(V[i]) }, V[i], E[i], unite);
      }
      zone.addEventListener('pointermove', function (e) {
        var r = svg.getBoundingClientRect();
        var i = Math.round((e.clientX - r.left - m.g) / (W - m.g - m.d) * (V.length - 1));
        viser(Math.max(0, Math.min(n, i)), e);
      });
      svg.addEventListener('pointerleave', function () { repere.setAttribute('visibility', 'hidden'); point.setAttribute('visibility', 'hidden'); cacher(); });
      svg.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault(); viser(Math.max(0, Math.min(n, courant + (e.key === 'ArrowRight' ? 1 : -1))));
      });
      svg.addEventListener('focus', function () { viser(courant); });
      svg.addEventListener('blur', function () { repere.setAttribute('visibility', 'hidden'); point.setAttribute('visibility', 'hidden'); cacher(); });
      return svg;
    },

    barres: function (box, E, V, unite) {
      var W = box.clientWidth, ligne = 52, epais = 18, H = V.length * ligne;
      var max = Math.max.apply(null, V), fin = W - 44;
      var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Rendez-vous par type de consultation' });
      V.forEach(function (v, i) {
        var y = i * ligne, l = Math.max(2, v / max * fin);
        var g = el('g', { tabindex: 0, class: 'marque', 'aria-label': E[i] + ' : ' + v + ' ' + unite }, svg);
        el('rect', { x: 0, y: y, width: W, height: ligne, fill: 'transparent' }, g);
        texte(g, 0, y + 15, E[i], { fill: C.encre, 'font-size': 13.5, 'font-weight': 550 });
        el('path', { d: barre(0, y + 24, l, epais, 'h'), fill: C.rouge, class: 'marque-forme' }, g);
        texte(g, l + 8, y + 24 + epais / 2 + 4.5, fr(v), { fill: C.encre, 'font-weight': 650, 'font-size': 13, 'font-variant-numeric': 'tabular-nums' });
        ['pointermove', 'focus'].forEach(function (t) { g.addEventListener(t, function (e) { montrer(e, v, E[i], unite); }); });
        ['pointerleave', 'blur'].forEach(function (t) { g.addEventListener(t, cacher); });
      });
      return svg;
    },

    colonnes: function (box, E, V, unite) {
      var W = box.clientWidth, H = 240, m = { g: 44, d: 8, h: 22, b: 28 };
      var max = Math.max.apply(null, V), pas = pasPropre(max, 3), haut = Math.ceil(max / pas) * pas;
      var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Ventes d’eBooks par mois' });
      var Y = function (v) { return m.h + (1 - v / haut) * (H - m.h - m.b); };
      for (var t = 0; t <= haut; t += pas) {
        el('line', { x1: m.g, x2: W - m.d, y1: Y(t), y2: Y(t), stroke: C.grille, 'stroke-width': 1 }, svg);
        texte(svg, m.g - 8, Y(t) + 4, fr(t), { 'text-anchor': 'end', 'font-variant-numeric': 'tabular-nums' });
      }
      var bande = (W - m.g - m.d) / V.length, epais = Math.min(24, bande * .5);
      V.forEach(function (v, i) {
        var cx = m.g + bande * (i + .5), dernier = i === V.length - 1;
        var g = el('g', { tabindex: 0, class: 'marque', 'aria-label': E[i] + ' : ' + v + ' ' + unite }, svg);
        el('rect', { x: cx - bande / 2, y: 0, width: bande, height: H, fill: 'transparent' }, g);
        el('path', { d: barre(cx - epais / 2, Y(v), epais, Y(0) - Y(v), 'v'), fill: dernier ? C.rouge : C.attenue, class: 'marque-forme' }, g);
        texte(g, cx, H - 8, E[i], { 'text-anchor': 'middle', fill: dernier ? C.encre : C.graphite, 'font-weight': dernier ? 650 : 400 });
        if (dernier) texte(g, cx, Y(v) - 8, fr(v), { 'text-anchor': 'middle', fill: C.encre, 'font-weight': 650, 'font-size': 13 });
        ['pointermove', 'focus'].forEach(function (t) { g.addEventListener(t, function (e) { montrer(e, v, E[i], unite); }); });
        ['pointerleave', 'blur'].forEach(function (t) { g.addEventListener(t, cacher); });
      });
      return svg;
    }
  };

  document.querySelectorAll('[data-graphe]').forEach(function (box) {
    var E = box.getAttribute('data-etiquettes').split(','), V = box.getAttribute('data-valeurs').split(',').map(Number);
    var unite = box.getAttribute('data-unite'), type = box.getAttribute('data-graphe'), largeur = 0;
    function dessiner() {
      if (box.clientWidth === largeur) return;
      largeur = box.clientWidth; box.textContent = '';
      var svg = DESSINS[type](box, E, V, unite);
      svg.style.width = svg.getAttribute('width') + 'px';
      svg.style.height = svg.getAttribute('height') + 'px';
      box.appendChild(svg);
    }
    dessiner();
    tableau(box, E, V, unite);
    if ('ResizeObserver' in window) new ResizeObserver(dessiner).observe(box);
  });
})();
