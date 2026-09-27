/* Rendus 3D des cellules sanguines — HEMATO GUI.
   Chaque scène est rendue sur fond transparent, en suréchantillonnage x2,
   puis exportée en PNG par rendre.mjs (?scene=<nom>). */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { mergeVertices } from 'three/addons/utils/BufferGeometryUtils.js';

const params = new URLSearchParams(location.search);
const NOM = params.get('scene') || 'hero-milieu';

/* ---------- hasard reproductible ---------- */
function mulberry32(a) {
  return function () {
    a |= 0; a = (a + 0x6D2B79F5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
let rnd = mulberry32(7);
const entre = (a, b) => a + (b - a) * rnd();

/* ---------- palette ---------- */
const ROUGE = '#c8102e';
const ROUGE_PROFOND = '#8b0a1e';

/* ---------- géométries ---------- */

// Globule rouge : disque biconcave (profil d'Evans & Fung, centre un peu épaissi)
function geoGlobule() {
  const demi = x => 0.5 * Math.sqrt(Math.max(0, 1 - x * x)) * (0.20 + 2.003 * x * x - 1.123 * x ** 4);
  const pts = [];
  const N = 90;
  for (let i = 0; i <= N; i++) {
    const x = Math.sin((i / N) * Math.PI / 2);
    pts.push(new THREE.Vector2(Math.max(x, 1e-4), demi(x)));
  }
  for (let i = N - 1; i >= 0; i--) {
    const x = Math.sin((i / N) * Math.PI / 2);
    pts.push(new THREE.Vector2(Math.max(x, 1e-4), -demi(x)));
  }
  const g = new THREE.LatheGeometry(pts, 128);
  g.computeVertexNormals();
  return g;
}

// Drépanocyte : croissant effilé, aplati
class Arc extends THREE.Curve {
  constructor(a0, a1) { super(); this.a0 = a0; this.a1 = a1; }
  getPoint(t, cible = new THREE.Vector3()) {
    const a = this.a0 + (this.a1 - this.a0) * t;
    return cible.set(Math.cos(a), Math.sin(a) * 0.92, 0);
  }
}
function geoDrepanocyte() {
  const courbe = new Arc(-1.95, 1.95);
  const TS = 200, RS = 48;
  const g = new THREE.TubeGeometry(courbe, TS, 0.36, RS, false);
  const pos = g.attributes.position;
  const v = new THREE.Vector3(), p = new THREE.Vector3();
  for (let k = 0; k < pos.count; k++) {
    const i = Math.floor(k / (RS + 1));
    const u = i / TS;
    courbe.getPoint(u, p);
    v.fromBufferAttribute(pos, k).sub(p);
    const effile = Math.pow(Math.sin(Math.PI * u), 0.75);
    v.multiplyScalar(effile);
    v.z *= 0.55;
    v.add(p);
    pos.setXYZ(k, v.x, v.y, v.z);
  }
  g.computeVertexNormals();
  return g;
}

// Leucocyte : sphère couverte de microvillosités
function geoLeucocyte(graine = 3) {
  const r = mulberry32(graine);
  let g = new THREE.IcosahedronGeometry(1, 60);
  g.deleteAttribute('normal'); g.deleteAttribute('uv');
  g = mergeVertices(g);
  const bosses = [];
  for (let i = 0; i < 420; i++) {
    const u = r() * 2 - 1, t = r() * Math.PI * 2, s = Math.sqrt(1 - u * u);
    bosses.push([s * Math.cos(t), s * Math.sin(t), u, 0.025 + r() * 0.05, 0.05 + r() * 0.06]);
  }
  const pos = g.attributes.position, v = new THREE.Vector3();
  for (let k = 0; k < pos.count; k++) {
    v.fromBufferAttribute(pos, k).normalize();
    let d = 0;
    for (const [x, y, z, a, s] of bosses) {
      const dx = v.x - x, dy = v.y - y, dz = v.z - z;
      const q = dx * dx + dy * dy + dz * dz;
      if (q < 0.09) d += a * Math.exp(-q / (s * s));
    }
    // grands lobes lents
    d += 0.035 * Math.sin(v.x * 3.1 + 1.2) * Math.sin(v.y * 2.7) * Math.cos(v.z * 2.3);
    v.multiplyScalar(1 + d);
    pos.setXYZ(k, v.x, v.y, v.z);
  }
  g.computeVertexNormals();
  return g;
}

// Plaquette : ellipsoïde aplati et irrégulier
function geoPlaquette(graine = 5) {
  const r = mulberry32(graine);
  let g = new THREE.IcosahedronGeometry(1, 24);
  g.deleteAttribute('normal'); g.deleteAttribute('uv');
  g = mergeVertices(g);
  const f1 = 1 + r() * 2, f2 = 1 + r() * 2, ph = r() * 6;
  const pos = g.attributes.position, v = new THREE.Vector3();
  for (let k = 0; k < pos.count; k++) {
    v.fromBufferAttribute(pos, k);
    const n = 1 + 0.09 * Math.sin(v.x * f1 * 3 + ph) * Math.cos(v.z * f2 * 3) + 0.05 * Math.sin(v.y * 7 + ph);
    v.multiplyScalar(n);
    v.y *= 0.34;
    pos.setXYZ(k, v.x, v.y, v.z * 0.85);
  }
  g.computeVertexNormals();
  return g;
}

// Goutte de sang
function geoGoutte() {
  const pts = [];
  const N = 120;
  for (let i = 0; i <= N; i++) {
    const t = i / N;               // 0 = pointe haute, 1 = bas
    let y, x;
    if (t < 0.55) {
      const s = t / 0.55;          // effilement du haut
      y = 1.75 - s * 1.75;
      x = Math.pow(Math.sin(s * Math.PI / 2), 1.6);
    } else {
      const s = (t - 0.55) / 0.45; // hémisphère du bas
      const a = s * Math.PI / 2;
      y = -Math.sin(a);
      x = Math.cos(a);
    }
    pts.push(new THREE.Vector2(Math.max(x, 1e-4), y));
  }
  const g = new THREE.LatheGeometry(pts, 128);
  g.computeVertexNormals();
  return g;
}

/* ---------- matériaux ---------- */
const matGlobule = (teinte = ROUGE) => new THREE.MeshPhysicalMaterial({
  color: teinte, roughness: 0.36, metalness: 0,
  clearcoat: 0.6, clearcoatRoughness: 0.3,
  sheen: 0.35, sheenColor: new THREE.Color('#ff4d63'), sheenRoughness: 0.5,
  emissive: new THREE.Color('#4a0010'), emissiveIntensity: 0.25,
});
const matLeucocyte = () => new THREE.MeshPhysicalMaterial({
  color: '#f4ecee', roughness: 0.62, clearcoat: 0.15,
  sheen: 1, sheenColor: new THREE.Color('#ffd9de'), sheenRoughness: 0.6,
});
const matPlaquette = () => new THREE.MeshPhysicalMaterial({
  color: '#eab3bb', roughness: 0.5, clearcoat: 0.3,
  sheen: 0.8, sheenColor: new THREE.Color('#fff0f2'),
});
const matGoutte = () => new THREE.MeshPhysicalMaterial({
  color: ROUGE, roughness: 0.12, clearcoat: 1, clearcoatRoughness: 0.08,
  emissive: new THREE.Color('#2a0006'), emissiveIntensity: 0.4,
});
const matFibrine = () => new THREE.MeshPhysicalMaterial({
  color: '#f1dde0', roughness: 0.55, sheen: 0.6, sheenColor: new THREE.Color('#ffffff'),
});
const matADN = (c) => new THREE.MeshPhysicalMaterial({
  color: c, roughness: 0.3, clearcoat: 0.8, clearcoatRoughness: 0.2,
});

/* ---------- mise en scène ---------- */
const params2 = { W: +(params.get('w') || 1200), H: +(params.get('h') || 1200) };
const SUR = 2; // suréchantillonnage
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
renderer.setPixelRatio(1);
renderer.setSize(params2.W * SUR, params2.H * SUR);
renderer.setClearColor(0x000000, 0);
renderer.toneMapping = THREE.NeutralToneMapping;
renderer.toneMappingExposure = 0.95;
renderer.outputColorSpace = THREE.SRGBColorSpace;
document.body.appendChild(renderer.domElement);

const scene = new THREE.Scene();
const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
scene.environmentIntensity = 0.5;

const cle = new THREE.DirectionalLight('#ffffff', 2.8); cle.position.set(-3, 5, 6); scene.add(cle);
const contre = new THREE.DirectionalLight('#ffc2cb', 2.6); contre.position.set(4, 2, -6); scene.add(contre);
const bas = new THREE.DirectionalLight('#ffffff', 0.6); bas.position.set(0, -5, 3); scene.add(bas);

const camera = new THREE.PerspectiveCamera(30, params2.W / params2.H, 0.1, 100);
camera.position.set(0, 0, 14);
camera.lookAt(0, 0, 0);

const G = { globule: geoGlobule() };

function globule(x, y, z, echelle = 1, rx = null, rz = null, teinte = ROUGE) {
  const m = new THREE.Mesh(G.globule, matGlobule(teinte));
  m.position.set(x, y, z);
  m.scale.setScalar(echelle);
  m.rotation.set(rx ?? entre(0.4, 1.3), entre(0, Math.PI), rz ?? entre(-0.8, 0.8));
  scene.add(m);
  return m;
}
function leucocyte(x, y, z, e = 1, graine = 3) {
  const m = new THREE.Mesh(geoLeucocyte(graine), matLeucocyte());
  m.position.set(x, y, z); m.scale.setScalar(e); m.rotation.set(0.3, 0.8, 0.1);
  scene.add(m); return m;
}
function plaquette(x, y, z, e = 0.3, graine = 5) {
  const m = new THREE.Mesh(geoPlaquette(graine), matPlaquette());
  m.position.set(x, y, z); m.scale.setScalar(e);
  m.rotation.set(entre(-1, 1), entre(0, 3), entre(-1, 1));
  scene.add(m); return m;
}

/* ---------- les scènes ---------- */
const SCENES = {
  // Héros : trois calques composés en CSS (arrière flouté, milieu net, avant flouté)
  'hero-arriere'() {
    rnd = mulberry32(11);
    camera.position.set(0, 0, 22);
    const pos = [[-4.6, 3.2], [3.8, 4.1], [-1.2, -4.4], [5.1, -2.2], [-5.4, -1.6], [1.4, 1.2], [0.2, 5.3], [4.6, 0.9]];
    pos.forEach(([x, y]) => globule(x, y, entre(-3, 0), entre(0.9, 1.25)));
  },
  'hero-milieu'() {
    rnd = mulberry32(21);
    camera.position.set(0, 0, 17.5);
    globule(-1.7, 2.3, 0.2, 1.6, 0.9, 0.5);
    globule(1.6, 1.3, -0.5, 1.5, 1.2, -0.4);
    globule(-0.2, -0.5, 1.3, 1.75, 0.62, 0.15);
    globule(2.7, -1.6, -0.8, 1.2, 0.8, 0.6);
    globule(-2.9, -1.9, -1.2, 1.1, 1.3, -0.3);
    globule(0.7, -3.2, -0.6, 1.05, 0.45, 0.5);
    globule(0.5, 3.4, -2.6, 0.9, 1.1, 0.1);
    leucocyte(-3.0, 0.5, -0.4, 1.05, 3);
    plaquette(1.5, -0.1, 2.4, 0.3, 5);
    plaquette(-1.4, -2.7, 1.5, 0.26, 8);
    plaquette(3.3, 0.4, 0.6, 0.24, 13);
    plaquette(-0.9, 1.1, 2.6, 0.22, 17);
    plaquette(2.1, 3.0, 0.2, 0.2, 19);
  },
  // Cartes des domaines d'expertise
  'drepanocytose'() {
    rnd = mulberry32(41);
    camera.position.set(0, 0, 12);
    const d = new THREE.Mesh(geoDrepanocyte(), matGlobule(ROUGE_PROFOND));
    d.position.set(-0.3, -0.2, 0.8); d.scale.setScalar(1.75); d.rotation.set(-0.45, 0.35, 0.55);
    scene.add(d);
    globule(1.9, 1.5, -1.5, 1.1, 1.0, 0.4);
    globule(-2.2, 1.9, -2.2, 0.8, 0.6, -0.3);
  },
  'hemophilie'() {
    rnd = mulberry32(51);
    camera.position.set(0, 0, 12);
    const g = new THREE.Mesh(geoGoutte(), matGoutte());
    g.position.set(0, -0.1, 0); g.scale.setScalar(1.65); g.rotation.set(0.12, 0, -0.1);
    scene.add(g);
    const p = new THREE.Mesh(geoGoutte(), matGoutte());
    p.position.set(2.1, -1.9, -0.6); p.scale.setScalar(0.5); p.rotation.set(0.1, 0, 0.2);
    scene.add(p);
    const q = new THREE.Mesh(geoGoutte(), matGoutte());
    q.position.set(-2.0, 1.9, -1.2); q.scale.setScalar(0.32); q.rotation.set(0.1, 0, -0.25);
    scene.add(q);
  },
  'cancers'() {
    rnd = mulberry32(61);
    camera.position.set(0, 0, 12);
    leucocyte(0, 0.1, 0.5, 1.75, 9);
    globule(-2.3, -1.7, -1.2, 1.1, 0.9, 0.5);
    globule(2.35, 1.6, -1.8, 0.95, 1.2, -0.4);
    globule(2.2, -2.0, 0.6, 0.7, 0.5, 0.2);
  },
  'hemostase'() {
    rnd = mulberry32(71);
    camera.position.set(0, 0, 12);
    const mat = matFibrine();
    for (let i = 0; i < 26; i++) {
      const pts = [];
      const a = entre(0, Math.PI);
      const cx = entre(-1.6, 1.6), cy = entre(-1.6, 1.6);
      for (let k = -3; k <= 3; k++) {
        pts.push(new THREE.Vector3(cx + Math.cos(a) * k * 1.05 + entre(-0.55, 0.55),
          cy + Math.sin(a) * k * 1.05 + entre(-0.55, 0.55), entre(-1.6, 1.4)));
      }
      const tube = new THREE.TubeGeometry(new THREE.CatmullRomCurve3(pts), 220, entre(0.014, 0.03), 10, false);
      scene.add(new THREE.Mesh(tube, mat));
    }
    globule(0.1, 0.0, 0.2, 1.35, 1.0, 0.35);
    globule(-2.2, 1.6, -1.2, 0.85, 0.7, -0.4);
    plaquette(1.5, 1.3, 0.8, 0.34, 5);
    plaquette(-1.3, -1.5, 0.9, 0.3, 8);
    plaquette(1.9, -1.1, 0.3, 0.26, 13);
    plaquette(-0.4, 1.7, 1.2, 0.24, 17);
  },
  'diagnostic'() {
    rnd = mulberry32(81);
    camera.position.set(0, 0, 13);
    const groupe = new THREE.Group();
    const R = 0.95, H = 7.2, tours = 2.1;
    const helice = (phase) => new THREE.CatmullRomCurve3(
      Array.from({ length: 200 }, (_, i) => {
        const t = i / 199, a = t * tours * Math.PI * 2 + phase;
        return new THREE.Vector3(Math.cos(a) * R, (t - 0.5) * H, Math.sin(a) * R);
      }));
    groupe.add(new THREE.Mesh(new THREE.TubeGeometry(helice(0), 400, 0.13, 20, false), matADN(ROUGE)));
    groupe.add(new THREE.Mesh(new THREE.TubeGeometry(helice(Math.PI), 400, 0.13, 20, false), matADN('#e9e9ee')));
    const nb = 26;
    for (let i = 1; i < nb; i++) {
      const t = i / nb, a = t * tours * Math.PI * 2;
      const p1 = new THREE.Vector3(Math.cos(a) * R, (t - 0.5) * H, Math.sin(a) * R);
      const p2 = new THREE.Vector3(Math.cos(a + Math.PI) * R, (t - 0.5) * H, Math.sin(a + Math.PI) * R);
      const milieu = p1.clone().add(p2).multiplyScalar(0.5);
      for (const [de, c] of [[p1, i % 2 ? '#e6a3ad' : '#d7d7de'], [p2, i % 2 ? '#d7d7de' : '#e6a3ad']]) {
        const dir = milieu.clone().sub(de);
        const cyl = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, dir.length(), 12), matADN(c));
        cyl.position.copy(de.clone().add(milieu).multiplyScalar(0.5));
        cyl.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir.normalize());
        groupe.add(cyl);
      }
    }
    groupe.rotation.set(0.2, 0.3, -0.62);
    scene.add(groupe);
  },
  // Globules seuls, pour les couvertures et les accents
  'globule-seul'() {
    rnd = mulberry32(91);
    camera.position.set(0, 0, 6.5);
    globule(0, 0, 0, 1.5, 0.85, 0.35);
  },
  'globule-profil'() {
    rnd = mulberry32(111);
    camera.position.set(0, 0, 6.2);
    globule(0, 0, 0, 1.45, 1.25, -0.35);
  },
  'trio'() {
    rnd = mulberry32(101);
    camera.position.set(0, 0, 11);
    globule(-1.2, 0.8, 0.3, 1.4, 0.9, 0.4);
    globule(1.4, -0.5, -0.4, 1.25, 1.2, -0.5);
    globule(-0.6, -1.9, 0.9, 0.9, 0.5, 0.2);
    plaquette(1.4, 1.6, 0.6, 0.26, 5);
  },
};

(SCENES[NOM] || SCENES['hero-milieu'])();
renderer.render(scene, camera);

// sous-échantillonnage x2 dans un canvas 2D : lissage des bords
const sortie = document.createElement('canvas');
sortie.width = params2.W; sortie.height = params2.H;
const ctx = sortie.getContext('2d');
ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
ctx.drawImage(renderer.domElement, 0, 0, params2.W, params2.H);
window.__png = sortie.toDataURL('image/png');
window.__fini = true;
