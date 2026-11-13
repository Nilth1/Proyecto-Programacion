/* =========================================================================
 * i18n.js  ·  FitPower
 * -------------------------------------------------------------------------
 * Sistema de traducción (i18n = "internationalization": i + 18 letras + n).
 *
 * Cómo funciona:
 *  1. El sitio está escrito en español. Cada idioma extra tiene su diccionario
 *     en js/i18n/<idioma>.js (por ahora: en.js).
 *  2. Cuando el idioma elegido es inglés, este script recorre la página y
 *     cambia cada texto que encuentre en el diccionario por su traducción
 *     (textos, placeholders, títulos, etc.).
 *  3. Un MutationObserver ("vigilante") detecta cuando los paneles redibujan
 *     algo y traduce lo nuevo al instante.
 *  4. También traduce los confirm()/alert() y pide las fechas y números al
 *     navegador en formato inglés.
 *  5. La elección se guarda en localStorage, así queda en todas las páginas.
 *
 * Uso: en el <head> de cada página, ANTES de los demás scripts:
 *   <script src="../js/i18n/en.js"></script>
 *   <script src="../js/i18n.js"></script>
 * ========================================================================= */
(function () {
  const CLAVE = 'fp_lang';
  const IDIOMAS = { es: 'Español', en: 'English' };

  let lang = 'es';
  try { lang = localStorage.getItem(CLAVE) || 'es'; } catch (_) {}
  if (!IDIOMAS[lang] || (lang !== 'es' && !(window.I18N && window.I18N[lang]))) lang = 'es';
  document.documentElement.lang = lang;

  const dic = lang === 'es' ? null : window.I18N[lang];
  const LOCALE = { es: 'es', en: 'en-US' };

  // "  Hola   mundo " -> "Hola mundo" (así se buscan en el diccionario)
  const normalizar = t => t.replace(/\s+/g, ' ').trim();

  /* ---------- Traducir un texto ---------- */
  const hechos = new Set();   // textos que ya produjo el traductor (para no re-traducirlos)
  function traducir(original) {
    if (!dic) return null;
    const t = normalizar(original);
    if (!t || hechos.has(t)) return null;
    let r = null;
    if (Object.prototype.hasOwnProperty.call(dic.textos, t)) r = dic.textos[t];
    else {
      for (const [re, rep] of dic.reglas) {
        const m = t.match(re);
        if (!m) continue;
        // las partes variables también se traducen si están en el diccionario ("baja" -> "low")
        const partes = m.slice(1).map(p => (p && dic.textos[normalizar(p)]) || p);
        r = typeof rep === 'function' ? rep(m[0], ...partes) : rep.replace(/\$(\d)/g, (_, i) => partes[i - 1] ?? '');
        break;
      }
      // "Pendiente · 3" -> se traduce cada parte por separado
      if (r === null && t.includes(' · ')) {
        const partes = t.split(' · ');
        const tr = partes.map(p => traducir(p) ?? p);
        if (tr.some((x, i) => x !== partes[i])) r = tr.join(' · ');
      }
    }
    if (r === null || r === t) return null;
    hechos.add(normalizar(r));
    // conserva los espacios de los costados del texto original
    const ini = original.match(/^\s*/)[0], fin = original.match(/\s*$/)[0];
    return ini + r + fin;
  }
  const t = texto => (lang === 'es' ? texto : (traducir(texto) ?? texto));

  /* ---------- Recorrer la página ---------- */
  // SALTEAR: etiquetas cuyo TEXTO no se traduce (lo que escribe el usuario, código, etc.)
  // SIN_ATRIBUTOS: etiquetas que ni siquiera tienen atributos para traducir.
  // Un <textarea> está en la primera pero no en la segunda: su placeholder SÍ se traduce.
  const SALTEAR = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE', 'NOSCRIPT']);
  const SIN_ATRIBUTOS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT']);
  const ATRIBUTOS = ['placeholder', 'title', 'aria-label', 'alt'];

  function traducirNodo(raiz) {
    if (!dic || !raiz) return;
    if (raiz.nodeType === 3) return traducirTexto(raiz);
    if (raiz.nodeType !== 1 || SIN_ATRIBUTOS.has(raiz.tagName) || raiz.closest('[data-no-i18n]')) return;
    traducirAtributos(raiz);
    if (SALTEAR.has(raiz.tagName)) return;
    raiz.querySelectorAll('*').forEach(el => { if (!SIN_ATRIBUTOS.has(el.tagName) && !el.closest('[data-no-i18n]')) traducirAtributos(el); });
    const walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
      acceptNode: n => (n.parentElement && (SALTEAR.has(n.parentElement.tagName) || n.parentElement.closest('[data-no-i18n]'))) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT,
    });
    const nodos = [];
    while (walker.nextNode()) nodos.push(walker.currentNode);
    nodos.forEach(traducirTexto);
  }
  function traducirTexto(n) {
    const r = traducir(n.nodeValue);
    if (r !== null) n.nodeValue = r;
  }
  function traducirAtributos(el) {
    for (const a of ATRIBUTOS) {
      const v = el.getAttribute && el.getAttribute(a);
      if (v) { const r = traducir(v); if (r !== null) el.setAttribute(a, r); }
    }
    // botones tipo <input type="submit" value="Ingresar">
    if (el.tagName === 'INPUT' && /^(submit|button)$/i.test(el.type) && el.value) {
      const r = traducir(el.value); if (r !== null) el.value = r;
    }
  }

  /* ---------- Fechas y números en el idioma elegido ---------- */
  if (lang !== 'es') {
    const cambiar = loc => (loc === undefined || /^es/i.test(String(loc))) ? LOCALE[lang] : loc;
    for (const [obj, metodo] of [[Date.prototype, 'toLocaleString'], [Date.prototype, 'toLocaleDateString'], [Date.prototype, 'toLocaleTimeString'], [Number.prototype, 'toLocaleString']]) {
      const original = obj[metodo];
      obj[metodo] = function (loc, opts) { return original.call(this, cambiar(loc), opts); };
    }
    // Ventanitas del navegador
    const conf = window.confirm, al = window.alert;
    window.confirm = m => conf.call(window, t(String(m)));
    window.alert = m => al.call(window, t(String(m)));
  }

  /* ---------- El botón con la bandera ---------- */
  const BANDERA_UK = '<svg viewBox="0 0 60 30" width="22" height="11" aria-hidden="true"><clipPath id="i18n-s"><path d="M0,0 v30 h60 v-30 z"/></clipPath><clipPath id="i18n-t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><g clip-path="url(#i18n-s)"><path d="M0,0 v30 h60 v-30 z" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#i18n-t)" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>';
  const BANDERA_UY = '<svg viewBox="0 0 27 18" width="22" height="15" aria-hidden="true"><rect width="27" height="18" fill="#fff"/><g fill="#0038A8"><rect x="13.5" y="2" width="13.5" height="2"/><rect x="13.5" y="6" width="13.5" height="2"/><rect y="10" width="27" height="2"/><rect y="14" width="27" height="2"/></g><g transform="translate(6.75 5)"><g stroke="#FCD116" stroke-width=".8"><path d="M0-4.2V4.2M-4.2 0H4.2M-3 -3L3 3M3 -3L-3 3"/></g><circle r="2.1" fill="#FCD116" stroke="#7B3F00" stroke-width=".3"/></g></svg>';

  function crearBoton() {
    if (document.getElementById('i18n-btn')) return;
    const css = document.createElement('style');
    css.textContent = `
      #i18n-btn{position:fixed;left:18px;bottom:18px;z-index:9998;display:flex;align-items:center;gap:8px;padding:8px 14px 8px 10px;
        border-radius:999px;border:1px solid rgba(255,255,255,.18);background:rgba(18,18,22,.82);color:#fff;cursor:pointer;
        font:700 12px/1 "Inter","Segoe UI",system-ui,sans-serif;letter-spacing:.6px;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
        box-shadow:0 8px 24px rgba(0,0,0,.35);transition:transform .15s,background .15s,border-color .15s}
      #i18n-btn:hover{transform:translateY(-2px);background:rgba(30,30,38,.92);border-color:rgba(47,107,255,.7)}
      #i18n-btn svg{border-radius:3px;box-shadow:0 0 0 1px rgba(255,255,255,.25);display:block}
      #i18n-btn .i18n-sub{font-weight:500;opacity:.65;letter-spacing:0}
      @media print{#i18n-btn{display:none}}`;
    document.head.appendChild(css);
    const b = document.createElement('button');
    b.id = 'i18n-btn';
    b.type = 'button';
    b.setAttribute('data-no-i18n', '');
    // En español se ofrece pasar a inglés (bandera del Reino Unido) y viceversa
    b.innerHTML = lang === 'es'
      ? `${BANDERA_UK}<span>EN</span><span class="i18n-sub">Translate</span>`
      : `${BANDERA_UY}<span>ES</span><span class="i18n-sub">Traducir</span>`;
    b.title = lang === 'es' ? 'Translate to English' : 'Ver en español';
    b.setAttribute('aria-label', b.title);
    b.addEventListener('click', () => cambiarIdioma(lang === 'es' ? 'en' : 'es'));
    document.body.appendChild(b);
  }

  function cambiarIdioma(nuevo) {
    try { localStorage.setItem(CLAVE, nuevo); } catch (_) {}
    // Se recarga para que todo (textos, fechas, mensajes) arranque en el idioma nuevo
    location.reload();
  }

  /* ---------- Arranque ---------- */
  function iniciar() {
    crearBoton();
    if (!dic) return;
    traducirNodo(document.body);
    const titulo = traducir(document.title); if (titulo !== null) document.title = titulo;
    // Vigilante: traduce lo que los paneles dibujan después (innerHTML, textContent, toasts...)
    new MutationObserver(cambios => {
      for (const c of cambios) {
        if (c.type === 'characterData') traducirTexto(c.target);
        else if (c.type === 'attributes') traducirAtributos(c.target);
        else c.addedNodes.forEach(traducirNodo);
      }
    }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATRIBUTOS });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();

  // API pública: I18N.t('texto') traduce desde cualquier script; I18N.idioma dice el actual
  window.I18N = Object.assign(window.I18N || {}, { t, idioma: lang, cambiarIdioma });
})();
