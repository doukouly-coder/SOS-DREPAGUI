/* HEMATO GUI — calculateurs de la page Outils hématologiques.
   Aides à la décision : les seuils sont indicatifs (OMS, IPSS-R de Greenberg 2012,
   CKD-EPI 2021 sans coefficient ethnique, Cockcroft-Gault). */
(function () {
  function nombre(v) { var n = parseFloat(String(v).replace(',', '.').replace(/\s/g, '')); return isFinite(n) ? n : NaN; }
  function fr(n, d) { return isFinite(n) ? n.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d }) : '—'; }
  function champ(calc, nom) { var el = calc.querySelector('[name="' + nom + '"]:checked') || calc.querySelector('[name="' + nom + '"]'); return el ? el.value : ''; }
  function sortie(calc, cle) { return calc.querySelector('[data-sortie="' + cle + '"]'); }
  function liste(ul, items) {
    ul.textContent = '';
    items.forEach(function (it) { var li = document.createElement('li'); li.textContent = it[1]; if (it[0]) li.className = it[0]; ul.appendChild(li); });
  }

  // Seuils d'anémie de l'OMS (g/dL) : [anémie si <, légère ≥, modérée ≥, sévère <]
  var SEUILS_HB = {
    homme: [13, 11, 8], femme: [12, 11, 8], enceinte: [11, 10, 7],
    e6: [11, 10, 7], e5: [11.5, 11, 8], e12: [12, 11, 8]
  };

  var CALCULS = {
    nfs: function (c) {
      var p = champ(c, 'profil'), hb = nombre(champ(c, 'hb')), vgm = nombre(champ(c, 'vgm'));
      var gb = nombre(champ(c, 'gb')), pnn = nombre(champ(c, 'pnn')), plq = nombre(champ(c, 'plq'));
      var s = SEUILS_HB[p], items = [], cytopenies = 0, titre = 'Pas d’anomalie majeure', note = '';
      var type = vgm < 80 ? 'microcytaire' : vgm > 100 ? 'macrocytaire' : 'normocytaire';
      if (hb < s[0]) {
        cytopenies++;
        var grav = hb >= s[1] ? 'légère' : hb >= s[2] ? 'modérée' : 'sévère';
        titre = 'Anémie ' + grav + ' ' + type;
        items.push(['alerte', 'Hémoglobine ' + fr(hb, 1) + ' g/dL : anémie ' + grav]);
        note = type === 'microcytaire' ? 'Explorer : ferritine et bilan martial, puis électrophorèse de l’hémoglobine.'
          : type === 'macrocytaire' ? 'Explorer : vitamine B12, folates, réticulocytes ; discuter un myélogramme.'
          : 'Explorer : réticulocytes, créatinine, CRP ; bilan d’hémolyse si réticulocytes élevés.';
      } else { items.push(['', 'Hémoglobine ' + fr(hb, 1) + ' g/dL : pas d’anémie']); }
      if (vgm < 80) items.push(['alerte', 'VGM ' + fr(vgm, 0) + ' fL : microcytose']);
      else if (vgm > 100) items.push(['alerte', 'VGM ' + fr(vgm, 0) + ' fL : macrocytose']);
      if (gb < 4) items.push(['alerte', 'Leucocytes ' + fr(gb, 1) + ' G/L : leucopénie']);
      else if (gb > 10) items.push(['alerte', 'Leucocytes ' + fr(gb, 1) + ' G/L : hyperleucocytose']);
      if (pnn < 0.5) { cytopenies++; items.push(['alerte', 'Neutrophiles ' + fr(pnn, 1) + ' G/L : neutropénie sévère, urgence si fièvre']); }
      else if (pnn < 1) { cytopenies++; items.push(['alerte', 'Neutrophiles ' + fr(pnn, 1) + ' G/L : neutropénie']); }
      else if (pnn < 1.5) items.push(['', 'Neutrophiles ' + fr(pnn, 1) + ' G/L : limite ; neutropénie ethnique bénigne possible (phénotype Duffy nul)']);
      if (plq < 150) { cytopenies++; items.push(['alerte', 'Plaquettes ' + fr(plq, 0) + ' G/L : thrombopénie' + (plq < 20 ? ' sévère' : '')]); }
      else if (plq > 400) items.push(['alerte', 'Plaquettes ' + fr(plq, 0) + ' G/L : thrombocytose']);
      if (cytopenies >= 2) { titre = (cytopenies === 3 ? 'Pancytopénie' : 'Bicytopénie'); note = 'Avis hématologique rapide ; frottis sanguin et myélogramme à discuter.'; }
      else if (plq > 400 && vgm < 80 && hb < s[0]) note += ' Thrombocytose probablement réactionnelle à la carence.';
      if (!isFinite(hb) || !isFinite(vgm)) { titre = 'Complétez les valeurs'; items = []; note = ''; }
      sortie(c, 'titre').textContent = titre;
      liste(sortie(c, 'liste'), items);
      sortie(c, 'note').textContent = note || 'Interpréter avec la clinique et les résultats antérieurs.';
    },

    formule: function (c) {
      var gb = nombre(champ(c, 'gb'));
      var lignes = [['pnn', 'Neutrophiles', 1.5, 7], ['ly', 'Lymphocytes', 1, 4], ['mo', 'Monocytes', 0.2, 1], ['eo', 'Éosinophiles', 0, 0.5], ['ba', 'Basophiles', 0, 0.1]];
      var tbody = sortie(c, 'table'), total = 0;
      tbody.textContent = '';
      lignes.forEach(function (l) {
        var pct = nombre(champ(c, l[0])); total += isFinite(pct) ? pct : 0;
        var abs = gb * pct / 100, tr = document.createElement('tr');
        var hors = abs < l[2] || abs > l[3];
        tr.innerHTML = '<td></td><td></td><td></td>';
        tr.children[0].textContent = l[1];
        tr.children[1].textContent = fr(abs, 2) + ' G/L';
        tr.children[2].textContent = isFinite(abs) ? (abs < l[2] ? 'bas' : abs > l[3] ? 'élevé' : 'normal') : '';
        if (hors && isFinite(abs)) tr.className = 'alerte';
        tbody.appendChild(tr);
      });
      sortie(c, 'note').textContent = Math.abs(total - 100) < 0.5
        ? 'Total des pourcentages : 100 %. Neutrophiles entre 1 et 1,5 G/L : penser à la neutropénie ethnique bénigne.'
        : 'Attention : le total des pourcentages fait ' + fr(total, 0) + ' %, vérifiez la saisie.';
    },

    clairance: function (c) {
      var h = champ(c, 'sexe') === 'h', age = nombre(champ(c, 'age')), poids = nombre(champ(c, 'poids'));
      var creat = nombre(champ(c, 'creat')), u = champ(c, 'unite');
      var umol = u === 'umol' ? creat : u === 'mgl' ? creat * 8.84 : creat * 88.4;
      var cg = (140 - age) * poids * (h ? 1.23 : 1.04) / umol;
      var scr = umol / 88.4, k = h ? 0.9 : 0.7, a = h ? -0.302 : -0.241;
      var ckd = 142 * Math.pow(Math.min(scr / k, 1), a) * Math.pow(Math.max(scr / k, 1), -1.2) * Math.pow(0.9938, age) * (h ? 1 : 1.012);
      sortie(c, 'cg').textContent = fr(cg, 0);
      sortie(c, 'ckd').textContent = fr(ckd, 0);
      var stade = !isFinite(ckd) ? '—' : ckd >= 90 ? 'G1' : ckd >= 60 ? 'G2' : ckd >= 45 ? 'G3a' : ckd >= 30 ? 'G3b' : ckd >= 15 ? 'G4' : 'G5';
      sortie(c, 'stade').textContent = 'Stade ' + stade + (ckd < 60 ? ' : adapter les doses des médicaments' : '');
    },

    ipssr: function (c) {
      var cyto = nombre(champ(c, 'cyto')), bl = nombre(champ(c, 'blastes')), hb = nombre(champ(c, 'hb'));
      var plq = nombre(champ(c, 'plq')), pnn = nombre(champ(c, 'pnn'));
      var score = cyto + (bl <= 2 ? 0 : bl < 5 ? 1 : bl <= 10 ? 2 : 3) + (hb >= 10 ? 0 : hb >= 8 ? 1 : 1.5)
        + (plq >= 100 ? 0 : plq >= 50 ? 0.5 : 1) + (pnn >= 0.8 ? 0 : 0.5);
      var risque = score <= 1.5 ? 'Risque très faible' : score <= 3 ? 'Risque faible' : score <= 4.5 ? 'Risque intermédiaire' : score <= 6 ? 'Risque élevé' : 'Risque très élevé';
      sortie(c, 'score').textContent = fr(score, 1);
      sortie(c, 'risque').textContent = isFinite(score) ? risque : '—';
    },

    mentzer: function (c) {
      var i = nombre(champ(c, 'vgm')) / nombre(champ(c, 'gr'));
      sortie(c, 'indice').textContent = fr(i, 1);
      sortie(c, 'orientation').textContent = !isFinite(i) ? '—' : i < 13 ? 'Évoque une thalassémie' : 'Évoque une carence martiale';
    }
  };

  document.querySelectorAll('[data-calc]').forEach(function (calc) {
    var f = CALCULS[calc.getAttribute('data-calc')];
    if (!f) return;
    var lancer = function () { f(calc); };
    calc.addEventListener('input', lancer);
    calc.addEventListener('change', lancer);
    lancer();
  });
})();
