/* =========================================================================
 * gifs.js  ·  FitPower
 * -------------------------------------------------------------------------
 * Muestra un GIF animado del ejercicio cuando pasás el mouse por arriba.
 *
 * Cómo se usa: a cualquier elemento del HTML le ponés el atributo
 *     data-gif="Nombre del ejercicio"
 * y listo, este script se encarga del resto.
 * Si el entrenador le subió un GIF propio, se agrega también
 *     data-gif-src="dist/assets/gifs/ej_xxxx.gif"
 * y se muestra ese en vez del automático.
 *
 * De dónde salen los GIFs: ExerciseDB (versión gratis, sin API key).
 *   - Los ejercicios que vienen en la base (init.sql) tienen su GIF fijo.
 *   - Si el entrenador crea uno nuevo, se traduce el nombre al inglés y se
 *     busca en la API. El resultado se guarda en localStorage para no
 *     volver a buscarlo.
 * ========================================================================= */
(function () {
  const MEDIA = 'https://static.exercisedb.dev/media/';
  const API_BUSCAR = 'https://oss.exercisedb.dev/api/v1/exercises/search?search=';
  const CACHE_KEY = 'fp_gifs_v1';

  /* ---------- 1) GIFs fijos para los ejercicios de init.sql ---------- */
  const FIJOS = {
    'sentadilla goblet': 'yn8yg1r',
    'flexiones de brazos': 'I4hDWkc',
    'remo con mancuerna': 'v2DfH14',
    'plancha abdominal': 'VBAWRPG',
    'press banca': 'EIeI8Vf',
    'aperturas con mancuernas': 'yz9nUhF',
    'fondos en banco': 'RrLske5',
    'dominadas asistidas': 'MaMuGH6',
    'curl de biceps': 'NbVPDMW',
    'sentadilla con barra': 'qXTaZnJ',
    'peso muerto rumano': 'wQ2c4XD',
    'puente de gluteos': 'u0cNiij',
    'burpees': 'dK9394r',
    'saltos de soga': 'e1e76I2',
    'mountain climbers': 'RJgzwny',
    'movilidad de cadera': '7WaDzyL',
    'rotacion de hombros': 'FWdVhcW',
    'elevacion de talones': 'bJYHBIN'
  };

  /* ---------- 2) Diccionario español -> inglés (para ejercicios nuevos) ----------
   * Primero se reemplazan las frases de varias palabras, después las sueltas. */
  const FRASES = [
    ['peso muerto', 'deadlift'], ['saltos de soga', 'jump rope'], ['salto de soga', 'jump rope'],
    ['elevacion de talones', 'calf raise'], ['elevaciones de talones', 'calf raise'],
    ['elevaciones laterales', 'lateral raise'], ['elevacion lateral', 'lateral raise'],
    ['elevaciones frontales', 'front raise'], ['elevacion frontal', 'front raise'],
    ['elevacion de piernas', 'leg raise'], ['elevaciones de piernas', 'leg raise'],
    ['hip thrust', 'hip thrust'], ['press militar', 'military press'], ['press de hombros', 'shoulder press'],
    ['press de banca', 'bench press'], ['press banca', 'bench press'], ['press inclinado', 'incline press'],
    ['jalon al pecho', 'lat pulldown'], ['jalon', 'pulldown'], ['prensa de piernas', 'leg press'],
    ['curl femoral', 'leg curl'], ['extension de cuadriceps', 'leg extension'], ['extension de piernas', 'leg extension'],
    ['extension de triceps', 'triceps extension'], ['press frances', 'skull crusher'],
    ['mountain climbers', 'mountain climber'], ['escaladores', 'mountain climber'],
    ['saltos de tijera', 'jumping jack'], ['jumping jacks', 'jumping jack'],
    ['cinta', 'treadmill'], ['bicicleta', 'bike']
  ];
  const PALABRAS = {
    sentadilla: 'squat', sentadillas: 'squat', flexiones: 'push-up', flexion: 'push-up', lagartijas: 'push-up',
    remo: 'row', plancha: 'plank', aperturas: 'fly', apertura: 'fly', fondos: 'dip', fondo: 'dip',
    dominadas: 'pull-up', dominada: 'pull-up', curl: 'curl', biceps: 'biceps', triceps: 'triceps',
    puente: 'bridge', gluteos: 'glute', gluteo: 'glute', burpees: 'burpee', burpee: 'burpee',
    zancadas: 'lunge', zancada: 'lunge', estocadas: 'lunge', estocada: 'lunge',
    abdominales: 'crunch', abdominal: 'crunch', crunch: 'crunch', encogimientos: 'shrug',
    mancuerna: 'dumbbell', mancuernas: 'dumbbell', barra: 'barbell', polea: 'cable', maquina: 'lever',
    kettlebell: 'kettlebell', pesa: 'kettlebell', rusa: '', banda: 'band', elastico: 'band',
    inclinado: 'incline', declinado: 'decline', martillo: 'hammer', predicador: 'preacher',
    asistidas: 'assisted', asistida: 'assisted', rumano: 'romanian', bulgara: 'bulgarian', sumo: 'sumo',
    hombros: 'shoulder', hombro: 'shoulder', pecho: 'chest', espalda: 'back', pierna: 'leg', piernas: 'leg',
    cadera: 'hip', talones: 'calf', gemelos: 'calf', pantorrillas: 'calf', rotacion: 'rotation',
    press: 'press', banco: 'bench', soga: 'rope', salto: 'jump', saltos: 'jump', caja: 'box',
    lateral: 'lateral', frontal: 'front', trasera: 'rear', invertido: 'reverse', inverso: 'reverse',
    rueda: 'rollout', russian: 'russian', twist: 'twist', giro: 'twist', giros: 'twist'
  };
  const MODIFICADORES = new Set(['dumbbell', 'barbell', 'cable', 'lever', 'kettlebell', 'band', 'incline', 'decline',
    'hammer', 'preacher', 'assisted', 'romanian', 'bulgarian', 'sumo', 'reverse', 'front', 'rear', 'military', 'lat']);
  const RELLENO = new Set(['de', 'del', 'con', 'en', 'la', 'el', 'los', 'las', 'al', 'a', 'y', 'para', 'sobre', 'un', 'una']);

  // "Curl de Bíceps" -> "curl de biceps" (minúsculas y sin tildes)
  const normalizar = t => String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();

  function traducir(nombre) {
    let t = ' ' + normalizar(nombre) + ' ';
    FRASES.forEach(([es, en]) => { t = t.split(' ' + es + ' ').join(' ' + en + ' '); });
    const palabras = [...new Set(t.trim().split(' ').filter(p => p && !RELLENO.has(p)).map(p => (p in PALABRAS ? PALABRAS[p] : p)).filter(Boolean))];
    // En inglés el equipo y los "adjetivos" van primero: "curl martillo" -> "hammer curl"
    const orden = [...MODIFICADORES], mods = palabras.filter(p => MODIFICADORES.has(p)).sort((x, y) => orden.indexOf(x) - orden.indexOf(y)), base = palabras.filter(p => !MODIFICADORES.has(p));
    return { completo: mods.concat(base).join(' '), base: base.join(' ') };
  }

  /* ---------- 3) Caché en localStorage (con try/catch por si el navegador lo bloquea) ---------- */
  let cache = {};
  try { cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '{}'); } catch (_) { cache = {}; }
  const guardarCache = () => { try { localStorage.setItem(CACHE_KEY, JSON.stringify(cache)); } catch (_) {} };

  // "push-ups" -> ["push", "up"]  (para comparar palabras sin importar plurales ni guiones)
  const tokens = t => normalizar(t).replace(/[^a-z0-9 ]/g, ' ').split(' ').filter(Boolean).map(w => w.replace(/s$/, ''));

  /* La búsqueda de la API es "difusa": siempre devuelve algo, aunque no tenga nada que ver.
   * Por eso elegimos nosotros el resultado que más palabras comparte con lo que buscamos,
   * y exigimos que tenga el movimiento principal (ej: "curl", "squat").
   * Devuelve: una URL, null (no hay GIF) o undefined (la API falló: no hay que cachearlo). */
  async function buscarEnApi(en) {
    let lista;
    try {
      const r = await fetch(API_BUSCAR + encodeURIComponent(en.completo));
      if (!r.ok) return undefined;
      const j = await r.json();
      lista = Array.isArray(j) ? j : (j.data || []);
    } catch (_) { return undefined; }   // sin internet o la API nos limitó por muchos pedidos

    const buscadas = tokens(en.completo), obligatorias = tokens(en.base);
    let mejor = null, mejorPuntos = 0;
    for (const ej of lista) {
      const nombre = tokens(ej.name);
      if (!obligatorias.every(w => nombre.includes(w))) continue;
      const puntos = buscadas.filter(w => nombre.includes(w)).length * 100 - nombre.length;  // más coincidencias y nombre más corto = mejor
      if (puntos > mejorPuntos || !mejor) { mejor = ej; mejorPuntos = puntos; }
    }
    return mejor ? (mejor.gifUrl || MEDIA + mejor.exerciseId + '.gif') : null;
  }

  // Devuelve la URL del GIF (o null si no hay) para un nombre en español
  async function urlDelGif(nombre, ignorarFijo) {
    const clave = normalizar(nombre);
    if (!ignorarFijo && FIJOS[clave]) return MEDIA + FIJOS[clave] + '.gif';
    if (clave in cache) return cache[clave];
    const en = traducir(nombre);
    if (!en.completo) return null;
    // Probamos con la traducción completa y, si no aparece nada, solo con el movimiento base
    let url = await buscarEnApi(en);
    if (url === null && en.base && en.base !== en.completo) url = await buscarEnApi({ completo: en.base, base: en.base });
    if (url === undefined) return null;      // la API falló: no lo guardamos, se reintenta la próxima vez
    cache[clave] = url; guardarCache();
    return url;
  }

  /* ---------- 4) El cartelito flotante ---------- */
  const css = document.createElement('style');
  css.textContent = `
    [data-gif]{cursor:help}
    [data-gif] .gif-ico{display:inline-block;margin-left:6px;font-size:10px;opacity:.55;vertical-align:middle}
    [data-gif]:hover .gif-ico{opacity:1}
    #gifPop{position:fixed;z-index:9999;pointer-events:none;width:200px;background:#fff;border-radius:14px;
      box-shadow:0 18px 40px rgba(0,0,0,.55);overflow:hidden;opacity:0;transform:translateY(6px) scale(.97);
      transition:opacity .15s,transform .15s;font-family:"Inter","Segoe UI",sans-serif}
    #gifPop.visible{opacity:1;transform:none}
    #gifPop .gif-caja{width:200px;height:200px;display:grid;place-items:center;background:#fff}
    #gifPop img{width:100%;height:100%;object-fit:contain;display:block}
    #gifPop .gif-txt{color:#555;font-size:12px;text-align:center;padding:0 16px}
    #gifPop .gif-nom{background:#141414;color:#fff;font-size:12px;font-weight:700;padding:9px 12px;text-align:center}
    #gifPop .gif-spin{width:28px;height:28px;border:3px solid #ddd;border-top-color:#2f6bff;border-radius:50%;animation:gifGira .8s linear infinite}
    @keyframes gifGira{to{transform:rotate(360deg)}}`;
  document.head.appendChild(css);

  const pop = document.createElement('div');
  pop.id = 'gifPop';
  pop.innerHTML = '<div class="gif-caja"></div><div class="gif-nom"></div>';
  const caja = pop.querySelector('.gif-caja'), titulo = pop.querySelector('.gif-nom');
  (document.body ? Promise.resolve() : new Promise(r => document.addEventListener('DOMContentLoaded', r)))
    .then(() => document.body.appendChild(pop));

  let actual = null;   // elemento sobre el que está el mouse ahora
  const mensaje = t => { caja.innerHTML = `<p class="gif-txt">${t}</p>`; };

  async function mostrar(el) {
    actual = el;
    const nombre = el.dataset.gif;
    titulo.textContent = nombre;
    caja.innerHTML = '<div class="gif-spin"></div>';
    pop.classList.add('visible');

    // 1° el GIF propio que subió el entrenador (si tiene), 2° el automático de ExerciseDB
    const propio = el.dataset.gifSrc;
    let url = propio ? (/^(https?:)?\//.test(propio) ? propio : '/' + propio) : await urlDelGif(nombre);
    if (actual !== el) return;               // el mouse ya se fue a otro lado
    if (!url) return mensaje('No encontré un GIF para este ejercicio 😕');

    const img = new Image();
    img.alt = nombre;
    img.onload = () => { if (actual === el) { caja.innerHTML = ''; caja.appendChild(img); } };
    img.onerror = async () => {
      // Si el GIF propio o el fijo no cargan, probamos con el automático / buscándolo en la API
      const otra = await urlDelGif(nombre, !propio);
      if (actual !== el) return;
      if (otra && otra !== img.src) { img.onerror = () => mensaje('No se pudo cargar el GIF'); img.src = otra; }
      else mensaje('No se pudo cargar el GIF');
    };
    img.src = url;
  }

  function ocultar() { actual = null; pop.classList.remove('visible'); }

  // Ubica el cartel al lado del mouse sin que se salga de la pantalla
  function mover(e) {
    const w = 200, h = pop.offsetHeight || 240, m = 18;
    let x = e.clientX + m, y = e.clientY + m;
    if (x + w > window.innerWidth - 8) x = e.clientX - w - m;
    if (y + h > window.innerHeight - 8) y = e.clientY - h - m;
    pop.style.left = Math.max(8, x) + 'px';
    pop.style.top = Math.max(8, y) + 'px';
  }

  // Delegación de eventos: funciona aunque el HTML se re-dibuje con innerHTML
  document.addEventListener('mouseover', e => {
    const el = e.target.closest('[data-gif]');
    if (el && el !== actual) { mover(e); mostrar(el); }
    else if (!el && actual) ocultar();
  });
  document.addEventListener('mousemove', e => { if (actual) mover(e); });
  document.addEventListener('mouseout', e => { if (!e.relatedTarget) ocultar(); });   // el mouse salió de la ventana
  document.addEventListener('scroll', ocultar, true);
  window.addEventListener('blur', ocultar);

  // Por si lo querés usar desde la consola: GIFS.url('Press banca').then(console.log)
  window.GIFS = { url: urlDelGif, traducir };
})();
