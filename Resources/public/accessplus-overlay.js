/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * vtinnovations/accessplus — comfort overlay widget (full feature set).
 *
 * Self-contained, no external resources, no inline handlers (CSP-safe), no
 * tokens, no tracking. Preferences live in localStorage only. Enabled features +
 * accent colour + position come from the mount point's data-* attributes.
 * Keyboard/ARIA-correct. Controls update in place (no rebuild → no scroll jump).
 *
 * COMFORT/display layer — never a substitute for an accessible site.
 */
(function () {
  'use strict';

  var HTML = document.documentElement;
  var KEY = 'accessplus_prefs';
  var prefs = {};
  var enabled = [];
  var root, panel, dyn, mag, guide, hovered;

  var LABELS = {
    profile_epilepsy: 'Epilepsie-sicherer Modus', profile_lowvision: 'Sehbehinderten-Modus', profile_adhd: 'ADHS-freundlicher Modus',
    contentscale: 'Inhaltliche Skalierung', fontsize: 'Schriftgröße', lineheight: 'Zeilenhöhe', letterspacing: 'Buchstabenabstand',
    readablefont: 'Lesbare Schriftart', dyslexiafont: 'Legasthenie-Schrift', highlighttitles: 'Titel hervorheben',
    highlightlinks: 'Links hervorheben', bionic: 'Kognitives Lesen', linknav: 'Link-Navigator',
    darkcontrast: 'Dunkler Kontrast', lightcontrast: 'Heller Kontrast', highcontrast: 'Hoher Kontrast', monochrome: 'Einfarbig',
    stopanim: 'Animationen stoppen', mutesound: 'Töne stummschalten', bigcursor: 'Großer Cursor', hideimages: 'Bilder ausblenden',
    readingguide: 'Lesehilfe', tts: 'Vorlesen', focushighlight: 'Fokus hervorheben', hoverhighlight: 'Hover hervorheben',
    textalign: 'Textausrichtung', color_text: 'Textfarbe', color_title: 'Titelfarbe', color_link: 'Linkfarbe', color_bg: 'Hintergrundfarbe'
  };
  var GROUPS = [
    ['Modi', ['profile_epilepsy', 'profile_lowvision', 'profile_adhd']],
    ['Lesen', ['contentscale', 'fontsize', 'lineheight', 'letterspacing', 'readablefont', 'dyslexiafont', 'highlighttitles', 'highlightlinks', 'bionic', 'linknav']],
    ['Orientierung', ['darkcontrast', 'lightcontrast', 'highcontrast', 'monochrome', 'stopanim', 'mutesound', 'bigcursor', 'hideimages', 'readingguide', 'tts', 'focushighlight', 'hoverhighlight', 'textalign']],
    ['Farben', ['color_text', 'color_title', 'color_link', 'color_bg']]
  ];
  var CLASSMAP = {
    readablefont: 'accessplus-readfont', dyslexiafont: 'accessplus-dysfont', highlighttitles: 'accessplus-hltitles',
    highlightlinks: 'accessplus-hllinks', highcontrast: 'accessplus-highcontrast', darkcontrast: 'accessplus-dark',
    lightcontrast: 'accessplus-light', monochrome: 'accessplus-mono', stopanim: 'accessplus-stopanim',
    hideimages: 'accessplus-hideimg', bigcursor: 'accessplus-bigcursor', focushighlight: 'accessplus-focus', hoverhighlight: 'accessplus-hoverhighlight'
  };
  var STEPPERS = { contentscale: [-3, 8], fontsize: [-3, 8], lineheight: [0, 6], letterspacing: [0, 6] };
  var PALETTE = ['#7b0000', '#e60000', '#26c6da', '#ff9800', '#ffeb3b', '#808000', '#1b8a1b', '#7b1fa2', '#ff1aff', '#00e676', '#00838f', '#1565c0', '#0d1b6b', '#000000', '#ffffff'];
  var PROFILES = {
    profile_epilepsy: { monochrome: true, stopanim: true },
    profile_lowvision: { fontsize: 2, highcontrast: true, readablefont: true, bigcursor: true },
    profile_adhd: { readingguide: true, stopanim: true }
  };
  var POS = ['bottomright', 'bottomleft', 'topright', 'topleft', 'middleright', 'middleleft'];
  // Mutually exclusive contrast modes — turning one on switches the others off.
  var CONTRAST_MODES = ['darkcontrast', 'lightcontrast', 'highcontrast'];

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }

  function init() {
    if (document.getElementById('accessplus-overlay-btn')) { return; }
    root = document.getElementById('accessplus-overlay-root');
    if (!root) { root = document.createElement('div'); root.id = 'accessplus-overlay-root'; }
    // Re-parent to <html> (outside <body>). If a theme ancestor has transform/
    // filter/perspective, position:fixed would otherwise anchor to that element
    // instead of the viewport — making the widget float mid-page.
    if (root.parentNode !== document.documentElement) { document.documentElement.appendChild(root); }
    enabled = (root.getAttribute('data-features') || '').split(',').filter(Boolean);
    if (!enabled.length) { enabled = Object.keys(LABELS); }
    var color = root.getAttribute('data-color');
    if (/^#[0-9a-f]{3,6}$/i.test(color || '')) { root.style.setProperty('--accessplus-accent', color); }
    var pos = root.getAttribute('data-position') || 'bottomright';
    root.classList.add('pos-' + (POS.indexOf(pos) !== -1 ? pos : 'bottomright'));
    dyn = document.createElement('style'); dyn.id = 'accessplus-dyn'; document.head.appendChild(dyn);
    prefs = load();
    applyAll();
    buildWidget();
    wireRuntime();
  }

  function load() { try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; } }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) { /* ignore */ } }
  function on(id) { return enabled.indexOf(id) !== -1; }
  function ishex(v) { return /^#[0-9a-f]{3,6}$/i.test(v); }

  function applyAll() {
    // Safety net: never more than one contrast mode active at once.
    var activeCM = CONTRAST_MODES.filter(function (m) { return prefs[m]; });
    if (activeCM.length > 1) { activeCM.slice(0, -1).forEach(function (m) { delete prefs[m]; }); }
    if (!prefs.hoverhighlight && hovered) { hovered.classList.remove('accessplus-hovered'); hovered = null; }

    Object.keys(CLASSMAP).forEach(function (id) { HTML.classList.toggle(CLASSMAP[id], !!prefs[id]); });
    HTML.classList.remove('accessplus-align-left', 'accessplus-align-center', 'accessplus-align-right');
    if (prefs.textalign) { HTML.classList.add('accessplus-align-' + prefs.textalign); }
    HTML.style.fontSize = prefs.fontsize ? (100 + prefs.fontsize * 10) + '%' : '';
    try { document.body.style.zoom = prefs.contentscale ? String(1 + prefs.contentscale * 0.1) : ''; } catch (e) { /* ignore */ }
    buildDyn();
    applyMedia();
    applyBionic(!!prefs.bionic);
    if (mag && !prefs.magnifier) { mag.style.display = 'none'; }
    if (guide) {
      if (prefs.readingguide) { guide.style.display = 'block'; if (!guide.style.top) { guide.style.top = '45vh'; } }
      else { guide.style.display = 'none'; }
    }
  }

  function buildDyn() {
    var c = '';
    if (prefs.lineheight) { c += 'body,p,li,td,dd,blockquote{line-height:' + (1.4 + prefs.lineheight * 0.35).toFixed(2) + ' !important;}'; }
    if (prefs.letterspacing) { c += 'body,p,li,td,a,span{letter-spacing:' + (prefs.letterspacing * 0.06).toFixed(2) + 'em !important;}'; }
    if (ishex(prefs.color_text)) { c += 'body,p,li,span,td{color:' + prefs.color_text + ' !important;}'; }
    if (ishex(prefs.color_title)) { c += 'h1,h2,h3,h4,h5,h6{color:' + prefs.color_title + ' !important;}'; }
    if (ishex(prefs.color_link)) { c += 'a{color:' + prefs.color_link + ' !important;}'; }
    if (ishex(prefs.color_bg)) { c += 'html,body{background:' + prefs.color_bg + ' !important;}'; }
    dyn.textContent = c;
  }

  function applyMedia() {
    var media = document.querySelectorAll('audio,video');
    for (var i = 0; i < media.length; i++) {
      if (prefs.mutesound) { try { media[i].muted = true; } catch (e) { /* ignore */ } }
      else { try { media[i].muted = false; } catch (e2) { /* ignore */ } }
      if (prefs.stopanim) { try { media[i].pause(); media[i].autoplay = false; } catch (e3) { /* ignore */ } }
    }
  }

  /* ── DOM helpers ─────────────────────────────────────────────── */
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    if (attrs) { Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); }); }
    if (text != null) { n.textContent = text; }
    return n;
  }
  function toast(msg) {
    var t = document.getElementById('accessplus-toast') || el('div', { id: 'accessplus-toast', role: 'status' });
    t.textContent = msg; document.documentElement.appendChild(t);
    clearTimeout(t._to); t._to = setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 5000);
  }

  function buildWidget() {
    var btn = el('button', { id: 'accessplus-overlay-btn', 'class': 'accessplus-btn', type: 'button',
      'aria-haspopup': 'dialog', 'aria-expanded': 'false', 'aria-controls': 'accessplus-panel',
      'aria-label': 'Anzeige-Einstellungen', title: 'Anzeige-Einstellungen' }, '⚙');

    panel = el('div', { 'class': 'accessplus-panel', id: 'accessplus-panel', role: 'dialog', 'aria-label': 'Anzeige-Einstellungen', hidden: 'hidden' });
    var head = el('div', { 'class': 'accessplus-panel-head' });
    head.appendChild(el('h2', null, 'Anzeige-Einstellungen'));
    var close = el('button', { type: 'button', 'class': 'accessplus-panel-close', 'aria-label': 'Schließen' }, '✕');
    close.addEventListener('click', function () { toggleOpen(btn); btn.focus(); });
    head.appendChild(close);
    panel.appendChild(head);
    panel.appendChild(el('p', { 'class': 'accessplus-note' }, 'Komfort-Optionen für die Darstellung. Kein Ersatz für barrierefreie Inhalte.'));

    GROUPS.forEach(function (g) {
      var ids = g[1].filter(on);
      if (!ids.length) { return; }
      panel.appendChild(el('div', { 'class': 'accessplus-group' }, g[0]));
      ids.forEach(function (id) { panel.appendChild(control(id)); });
    });

    var reset = el('button', { 'class': 'accessplus-reset', type: 'button' }, 'Alles zurücksetzen');
    reset.addEventListener('click', function () { prefs = {}; save(); applyAll(); refreshControls(); });
    panel.appendChild(reset);

    btn.addEventListener('click', function () { toggleOpen(btn); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { toggleOpen(btn); btn.focus(); }
    });

    root.appendChild(btn);
    root.appendChild(panel);
  }

  function toggleOpen(btn) {
    var open = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    if (open) { panel.setAttribute('hidden', 'hidden'); }
    else {
      // Re-append as the LAST child of <html>: if some other page widget also
      // claims the max z-index (2147483647), equal z-index ties break by DOM
      // order, so being last keeps us painted on top no matter what loaded
      // after us.
      document.documentElement.appendChild(root);
      resetPanelPosition();
      panel.removeAttribute('hidden');
      clampPanel();
      var f = panel.querySelector('button,select'); if (f) { f.focus(); }
    }
  }

  function resetPanelPosition() {
    panel.style.position = ''; panel.style.left = ''; panel.style.top = '';
    panel.style.right = ''; panel.style.bottom = ''; panel.style.transform = '';
    panel.style.maxWidth = ''; panel.style.maxHeight = '';
  }

  /* Safety net: after CSS lays the panel out near the toggle button (which
     depends on the configured position + the button's on-screen spot), pull it
     fully back on-screen if any edge would overflow the viewport. Runs on every
     open and on resize/rotation, so the panel never breaks — regardless of
     screen size, host page zoom, or which of the 6 position presets is set.
     Note: #accessplus-overlay-root is `position:fixed`, so it is ALWAYS the
     containing block for the panel — for the middle-left/middle-right presets
     it also carries a `transform` (for vertical centering), which per spec
     makes it the containing block even for a `position:fixed` descendant. So
     the clamped coordinates must stay in root-relative space (position:
     absolute), never viewport-relative position:fixed, or they'd be applied
     relative to the wrong origin on those two presets. */
  function clampPanel() {
    if (panel.hasAttribute('hidden')) { return; }
    var margin = 8;
    var vw = document.documentElement.clientWidth;
    var vh = document.documentElement.clientHeight;
    var r = panel.getBoundingClientRect();
    if (r.width > vw - margin * 2) { panel.style.maxWidth = (vw - margin * 2) + 'px'; r = panel.getBoundingClientRect(); }
    if (r.height > vh - margin * 2) { panel.style.maxHeight = (vh - margin * 2) + 'px'; r = panel.getBoundingClientRect(); }
    var left = r.left, top = r.top;
    if (left < margin) { left = margin; }
    else if (left + r.width > vw - margin) { left = Math.max(margin, vw - margin - r.width); }
    if (top < margin) { top = margin; }
    else if (top + r.height > vh - margin) { top = Math.max(margin, vh - margin - r.height); }
    if (left !== r.left || top !== r.top) {
      var rootRect = root.getBoundingClientRect();
      panel.style.position = 'absolute';
      panel.style.left = (left - rootRect.left) + 'px';
      panel.style.top = (top - rootRect.top) + 'px';
      panel.style.right = 'auto'; panel.style.bottom = 'auto'; panel.style.transform = 'none';
    }
  }

  /* Refresh all control states in place (no DOM rebuild → no scroll jump). */
  function refreshControls() {
    panel.querySelectorAll('.accessplus-switch[data-pref]').forEach(function (b) {
      b.setAttribute('aria-pressed', prefs[b.getAttribute('data-pref')] ? 'true' : 'false');
    });
    panel.querySelectorAll('.accessplus-switch[data-profile]').forEach(function (b) {
      b.setAttribute('aria-pressed', profileActive(b.getAttribute('data-profile')) ? 'true' : 'false');
    });
    panel.querySelectorAll('output[data-step]').forEach(function (o) { o.textContent = fmt(o.getAttribute('data-step')); });
    panel.querySelectorAll('button[data-align]').forEach(function (b) {
      b.setAttribute('aria-pressed', prefs.textalign === b.getAttribute('data-align') ? 'true' : 'false');
    });
  }

  /* ── Controls ────────────────────────────────────────────────── */
  function control(id) {
    if (id.indexOf('profile_') === 0) { return profileRow(id); }
    if (STEPPERS[id]) { return stepperRow(id); }
    if (id.indexOf('color_') === 0) { return colorRow(id); }
    if (id === 'textalign') { return alignRow(); }
    if (id === 'linknav') { return linkNavRow(); }
    return toggleRow(id);
  }
  function row(labelText) { var r = el('div', { 'class': 'accessplus-row' }); r.appendChild(el('span', null, labelText)); return r; }

  function toggleRow(id) {
    var r = row(LABELS[id]);
    var b = el('button', { type: 'button', 'class': 'accessplus-switch', 'data-pref': id, 'aria-pressed': prefs[id] ? 'true' : 'false', 'aria-label': LABELS[id] });
    b.addEventListener('click', function () {
      prefs[id] = !prefs[id];
      // Contrast modes are mutually exclusive — clear the others when enabling.
      if (prefs[id] && CONTRAST_MODES.indexOf(id) !== -1) {
        CONTRAST_MODES.forEach(function (m) { if (m !== id) { delete prefs[m]; } });
      }
      save(); applyAll(); refreshControls();
      if (id === 'tts' && prefs[id]) { toast('Markieren Sie Text oder klicken Sie ein Element an, um es vorlesen zu lassen.'); }
      if (id === 'mutesound' && prefs[id]) { toast('Medien werden stummgeschaltet.'); }
    });
    r.appendChild(b);
    return r;
  }

  function profileRow(id) {
    var r = row(LABELS[id]);
    var b = el('button', { type: 'button', 'class': 'accessplus-switch', 'data-profile': id, 'aria-pressed': profileActive(id) ? 'true' : 'false', 'aria-label': LABELS[id] });
    b.addEventListener('click', function () {
      var nowOn = !profileActive(id);
      Object.keys(PROFILES[id]).forEach(function (k) { if (nowOn) { prefs[k] = PROFILES[id][k]; } else { delete prefs[k]; } });
      save(); applyAll(); refreshControls();
    });
    r.appendChild(b);
    return r;
  }
  function profileActive(id) { return Object.keys(PROFILES[id]).every(function (k) { return prefs[k] === PROFILES[id][k]; }); }

  function stepperRow(id) {
    var range = STEPPERS[id];
    var r = row(LABELS[id]);
    var grp = el('div', { 'class': 'accessplus-step' });
    var minus = el('button', { type: 'button', 'aria-label': LABELS[id] + ' verringern' }, '−');
    var out = el('output', { 'data-step': id }, fmt(id));
    var plus = el('button', { type: 'button', 'aria-label': LABELS[id] + ' erhöhen' }, '+');
    minus.addEventListener('click', function () { step(id, -1, range, out); });
    plus.addEventListener('click', function () { step(id, 1, range, out); });
    grp.appendChild(minus); grp.appendChild(out); grp.appendChild(plus);
    r.appendChild(grp);
    return r;
  }
  function step(id, d, range, out) {
    var v = (prefs[id] || 0) + d;
    v = Math.max(range[0], Math.min(range[1], v));
    if (v === 0) { delete prefs[id]; } else { prefs[id] = v; }
    save(); applyAll(); out.textContent = fmt(id);
  }
  function fmt(id) { var v = prefs[id] || 0; return v === 0 ? 'Standard' : (v > 0 ? '+' + v : String(v)); }

  function alignRow() {
    var r = row(LABELS.textalign);
    var grp = el('div', { 'class': 'accessplus-align' });
    [['left', 'Links'], ['center', 'Mitte'], ['right', 'Rechts']].forEach(function (a) {
      var b = el('button', { type: 'button', 'data-align': a[0], 'aria-pressed': prefs.textalign === a[0] ? 'true' : 'false' }, a[1]);
      b.addEventListener('click', function () {
        prefs.textalign = prefs.textalign === a[0] ? '' : a[0];
        if (!prefs.textalign) { delete prefs.textalign; }
        save(); applyAll(); refreshControls();
      });
      grp.appendChild(b);
    });
    r.appendChild(grp);
    return r;
  }

  function colorRow(id) {
    var wrap = el('div');
    wrap.appendChild(el('div', { 'class': 'accessplus-group', style: 'border:0;color:#333;font-weight:600;' }, LABELS[id]));
    var sw = el('div', { 'class': 'accessplus-swatches' });
    PALETTE.forEach(function (col) {
      var b = el('button', { type: 'button', 'class': 'accessplus-swatch', 'aria-label': LABELS[id] + ' ' + col, title: col, style: 'background:' + col });
      b.addEventListener('click', function () { prefs[id] = col; save(); buildDyn(); });
      sw.appendChild(b);
    });
    var clear = el('button', { type: 'button', 'class': 'accessplus-swatch accessplus-clear' }, 'Standard');
    clear.addEventListener('click', function () { delete prefs[id]; save(); buildDyn(); });
    sw.appendChild(clear);
    wrap.appendChild(sw);
    return wrap;
  }

  function linkNavRow() {
    var r = row(LABELS.linknav);
    var sel = el('select', { 'aria-label': LABELS.linknav });
    sel.appendChild(el('option', { value: '' }, 'Link wählen …'));
    var links = document.querySelectorAll('a[href]');
    var n = 0;
    for (var i = 0; i < links.length && n < 300; i++) {
      var a = links[i];
      var t = (a.textContent || '').trim();
      if (!t || a.closest('#accessplus-overlay-root') || !visible(a)) { continue; }
      a.setAttribute('data-accessplus-link', String(n));
      sel.appendChild(el('option', { value: String(n) }, t.substring(0, 60)));
      n++;
    }
    sel.addEventListener('change', function () {
      var target = document.querySelector('a[data-accessplus-link="' + sel.value + '"]');
      if (target) { target.scrollIntoView({ block: 'center' }); target.focus(); }
    });
    r.appendChild(sel);
    return r;
  }
  function visible(node) {
    if (!node.getClientRects().length) { return false; }
    var s = window.getComputedStyle(node);
    return s.visibility !== 'hidden' && s.display !== 'none' && s.opacity !== '0';
  }

  /* ── Runtime behaviours ──────────────────────────────────────── */
  function wireRuntime() {
    window.addEventListener('resize', clampPanel, { passive: true });
    window.addEventListener('orientationchange', function () { setTimeout(clampPanel, 100); });

    mag = el('div', { id: 'accessplus-magnifier' });
    guide = el('div', { id: 'accessplus-guide' });
    document.documentElement.appendChild(mag);
    document.documentElement.appendChild(guide);
    if (prefs.readingguide) { guide.style.display = 'block'; guide.style.top = '40vh'; }

    document.addEventListener('mousemove', function (e) {
      if (prefs.readingguide) { guide.style.display = 'block'; guide.style.top = (e.clientY - 20) + 'px'; }
      if (prefs.magnifier || prefs.hoverhighlight) {
        var t = document.elementFromPoint(e.clientX, e.clientY);
        if (prefs.hoverhighlight) {
          if (hovered && hovered !== t) { hovered.classList.remove('accessplus-hovered'); }
          if (t && !t.closest('#accessplus-overlay-root')) { t.classList.add('accessplus-hovered'); hovered = t; }
        }
        if (prefs.magnifier && t && !t.closest('#accessplus-overlay-root')) {
          var txt = (t.innerText || t.textContent || '').trim();
          if (txt) {
            mag.textContent = txt.substring(0, 200); mag.style.display = '';
            mag.style.left = Math.min(e.clientX + 16, window.innerWidth - 320) + 'px';
            mag.style.top = Math.min(e.clientY + 16, window.innerHeight - 80) + 'px';
          } else { mag.style.display = 'none'; }
        }
      } else if (mag.style.display !== 'none') { mag.style.display = 'none'; }
    }, { passive: true });

    document.addEventListener('click', function (e) {
      if (!prefs.tts || !window.speechSynthesis) { return; }
      var t = e.target;
      if (!t || t.closest('#accessplus-overlay-root')) { return; }
      var sel = (window.getSelection && window.getSelection().toString()) || '';
      var txt = (sel || t.innerText || t.textContent || '').trim();
      if (!txt) { return; }
      window.speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(txt.substring(0, 1200));
      u.lang = document.documentElement.lang || 'de';
      window.speechSynthesis.speak(u);
    }, true);
  }

  /* Bionic reading: bold the leading part of each word. Reversible. */
  function applyBionic(want) {
    var isOn = document.querySelector('.accessplus-bionic-wrap') !== null;
    if (want === isOn) { return; }
    if (!want) {
      document.querySelectorAll('.accessplus-bionic-wrap').forEach(function (w) { w.parentNode.replaceChild(document.createTextNode(w.textContent), w); });
      return;
    }
    document.querySelectorAll('p,li,h1,h2,h3,h4,h5,h6,td,dd,blockquote').forEach(function (node) {
      if (node.closest('#accessplus-overlay-root')) { return; }
      for (var i = 0; i < node.childNodes.length; i++) {
        var ch = node.childNodes[i];
        if (ch.nodeType === 3 && ch.nodeValue.trim()) {
          var span = el('span', { 'class': 'accessplus-bionic-wrap' });
          ch.nodeValue.split(/(\s+)/).forEach(function (w) {
            if (/^\s+$/.test(w) || !w) { span.appendChild(document.createTextNode(w)); return; }
            var k = Math.max(1, Math.ceil(w.length * 0.4));
            span.appendChild(el('b', { 'class': 'accessplus-bionic-b' }, w.substring(0, k)));
            span.appendChild(document.createTextNode(w.substring(k)));
          });
          node.replaceChild(span, ch);
          i++;
        }
      }
    });
  }
})();
