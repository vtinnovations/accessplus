/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * vtinnovations/accessplus — combined full scan orchestrator (dashboard).
 *
 * One button: first triggers the server-side database analysis, then runs the
 * client-side axe frontend scan (each published page in a same-origin iframe).
 * A progress bar + phase label show which part is running. axe-core is served
 * from this bundle's own assets — no third-party script is loaded remotely.
 */
(function () {
  'use strict';

  var cfg = window.VTA11Y_FULLSCAN;
  if (!cfg) {
    return;
  }

  var btn = document.getElementById('accessplus-fs-start');
  var phase = document.getElementById('accessplus-fs-phase');
  var note = document.getElementById('accessplus-fs-note');
  var bar = document.getElementById('accessplus-fs-bar');
  var fill = document.getElementById('accessplus-fs-fill');
  var frame = document.getElementById('accessplus-fs-frame');
  var axeSource = null;

  function setPhase(t) { if (phase) { phase.textContent = t; } }
  function setNote(t) { if (note) { note.textContent = t; } }
  function setPct(p) { if (fill) { fill.style.width = Math.max(0, Math.min(100, p)) + '%'; } }
  function showBar() { if (bar) { bar.hidden = false; } }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      // X-Requested-With marks this as an Ajax request so Contao's
      // RequestTokenListener skips its body-only REQUEST_TOKEN check (we send the
      // token in the header and validate it in the controller). Without it, some
      // setups treat the POST as a simple-CORS request → 400 InvalidRequestToken.
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Contao-Request-Token': cfg.token
      },
      body: JSON.stringify(body || {})
    });
  }

  function getAxe() {
    if (axeSource) { return Promise.resolve(axeSource); }
    return fetch(cfg.axeUrl).then(function (r) { return r.text(); }).then(function (s) { axeSource = s; return s; });
  }

  // Scan in batches: after every BATCH_SIZE pages, pause so the server/DB and the
  // browser can breathe (and any lingering media is released) before continuing.
  var BATCH_SIZE = 10;
  var BATCH_PAUSE = 2500;

  function wait(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
  }

  // Collect all <video>/<audio> under a root, piercing open shadow DOM (theme
  // players are often web components whose media lives inside a shadow root).
  function collectMedia(root, out) {
    try {
      var els = root.querySelectorAll('*');
      for (var i = 0; i < els.length; i++) {
        var tag = els[i].tagName;
        if (tag === 'VIDEO' || tag === 'AUDIO') { out.push(els[i]); }
        if (els[i].shadowRoot) { collectMedia(els[i].shadowRoot, out); }
      }
    } catch (e) { /* ignore */ }
    return out;
  }

  // Silence autoplay media so the backend tab does not blast sound while
  // sweeping pages:
  //   • native <video>/<audio> (incl. open shadow DOM) → muted + paused
  //   • same-origin nested iframes → recursed and muted directly
  //   • cross-origin iframes (ANY embed) → src blanked to about:blank
  // Blanking is safe: axe cannot read across origins anyway, so it never scanned
  // inside those frames; the <iframe> element and its title stay in the parent
  // DOM, so the frame-title check is unaffected. This guarantees silence from any
  // player (YouTube, Vimeo, Cloudflare Stream, self-hosted widgets, unknown…).
  function muteMedia(doc) {
    try {
      var media = collectMedia(doc, []);
      for (var i = 0; i < media.length; i++) {
        try {
          media[i].muted = true;
          media[i].volume = 0;
          media[i].autoplay = false;
          media[i].removeAttribute('autoplay');
          media[i].pause();
        } catch (e) { /* ignore individual element errors */ }
      }
      var frames = doc.querySelectorAll('iframe');
      for (var j = 0; j < frames.length; j++) {
        var f = frames[j];
        var sameOrigin = false;
        try { sameOrigin = !!f.contentDocument; } catch (e) { sameOrigin = false; }
        if (sameOrigin) {
          muteMedia(f.contentDocument); // reachable → mute its media directly
          continue;
        }
        // Cross-origin embed: unreachable DOM → blank it (once) to kill any audio.
        try {
          if (f.getAttribute('data-accessplus-muted') === '1') { continue; }
          f.setAttribute('data-accessplus-muted', '1');
          f.src = 'about:blank';
        } catch (e) { /* ignore */ }
      }
    } catch (e) { /* ignore */ }
  }

  // ── Accessible-name (ARIA label) candidates ────────────────────────────
  // For name-related axe rules, derive a suggested accessible name from the live
  // element (the browser has the real DOM) and send it to the aria-ingest so the
  // editor gets a prefilled proposal.
  var NAME_RULES = {
    'link-name': 1, 'button-name': 1, 'frame-title': 1, 'aria-input-field-name': 1,
    'input-button-name': 1, 'aria-command-name': 1, 'aria-toggle-field-name': 1, 'input-image-alt': 1
  };
  var SOCIAL = [
    ['facebook.', 'Facebook'], ['instagram.', 'Instagram'], ['youtube.', 'YouTube'], ['youtu.be', 'YouTube'],
    ['twitter.', 'Twitter/X'], ['x.com', 'Twitter/X'], ['linkedin.', 'LinkedIn'], ['xing.', 'Xing'],
    ['tiktok.', 'TikTok'], ['pinterest.', 'Pinterest'], ['whatsapp.', 'WhatsApp'], ['wa.me', 'WhatsApp'], ['vimeo.', 'Vimeo']
  ];

  function nameFromUrl(url, fallback) {
    if (!url) { return fallback; }
    var l = url.toLowerCase();
    for (var i = 0; i < SOCIAL.length; i++) { if (l.indexOf(SOCIAL[i][0]) >= 0) { return SOCIAL[i][1]; } }
    try {
      var a = document.createElement('a');
      a.href = url;
      var seg = (a.pathname || '').split('/').filter(Boolean).pop() || '';
      seg = decodeURIComponent(seg).replace(/\.[a-z0-9]{1,5}$/i, '').replace(/[-_]+/g, ' ').trim();
      if (seg) { return seg.charAt(0).toUpperCase() + seg.slice(1); }
      var host = (a.hostname || '').replace(/^www\./, '');
      return host || fallback;
    } catch (e) { return fallback; }
  }

  function suggestName(el) {
    try {
      var title = el.getAttribute('title');
      if (title && title.trim()) { return title.trim(); }
      var img = el.querySelector && el.querySelector('img[alt]');
      if (img) { var al = (img.getAttribute('alt') || '').trim(); if (al) { return al; } }
      var tag = (el.tagName || '').toLowerCase();
      if (tag === 'iframe') { return nameFromUrl(el.getAttribute('src') || '', 'Eingebetteter Inhalt'); }
      if (tag === 'a') {
        var href = el.getAttribute('href') || '';
        if (href.indexOf('mailto:') === 0) { return 'E-Mail: ' + href.slice(7).split('?')[0]; }
        if (href.indexOf('tel:') === 0) { return 'Anruf: ' + href.slice(4); }
        var n = nameFromUrl(href, ''); if (n) { return n; }
      }
      var txt = (el.textContent || '').trim();
      if (txt) { return txt.slice(0, 80); }
    } catch (e) { /* ignore */ }
    return '';
  }

  function selectorOf(target) {
    if (typeof target === 'string') { return target; }
    if (!target || !target.length) { return ''; }
    var parts = [];
    for (var i = 0; i < target.length; i++) {
      var t = target[i];
      if (typeof t === 'string') { parts.push(t); }
      else if (t && t.length) { for (var k = 0; k < t.length; k++) { if (typeof t[k] === 'string') { parts.push(t[k]); } } }
    }
    return parts.join(' ').slice(0, 512);
  }

  function collectAria(doc, result, pageUrl) {
    var out = [];
    var vs = (result && result.violations) || [];
    for (var i = 0; i < vs.length; i++) {
      var v = vs[i];
      if (!v || !NAME_RULES[v.id]) { continue; }
      var nodes = v.nodes || [];
      for (var j = 0; j < nodes.length && j < 25; j++) {
        var node = nodes[j];
        var sel = selectorOf(node.target);
        if (!sel) { continue; }
        var el = null;
        try { el = doc.querySelector(sel); } catch (e) { /* invalid selector */ }
        out.push({
          selector: sel, url: pageUrl, ruleId: v.id,
          html: (node.html || '').slice(0, 600),
          suggestion: el ? suggestName(el) : ''
        });
      }
    }
    return out;
  }

  function loadPage(url) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { reject(new Error('timeout')); }, 30000);
      frame.onload = function () { clearTimeout(timer); resolve(); };
      frame.onerror = function () { clearTimeout(timer); reject(new Error('load_error')); };
      frame.src = url;
    });
  }

  // Let the page settle: scroll through it so scroll-triggered reveal
  // animations (IntersectionObserver) fire and styles reach their final state,
  // then return to top. Avoids false contrast/visibility violations on elements
  // that are still in their pre-reveal state.
  function settle(win, doc) {
    return new Promise(function (resolve) {
      try {
        var h = (doc.body && doc.body.scrollHeight) || 0;
        var y = 0;
        var step = function () {
          muteMedia(doc); // re-mute: autoplay can start as content scrolls into view
          y += 600;
          win.scrollTo(0, y);
          if (y < h) {
            setTimeout(step, 120);
          } else {
            win.scrollTo(0, 0);
            setTimeout(resolve, 1200);
          }
        };
        setTimeout(step, 300);
      } catch (e) {
        setTimeout(resolve, 1200);
      }
    });
  }

  function scanPage(page, src, scanStart) {
    // Keep re-muting for the whole time this page is loaded: some players start
    // audio via JS after load or on a delay, so a one-shot mute is not enough.
    var muteTimer = null;
    var pageDoc = null;
    function stopMuteLoop() { if (muteTimer) { clearInterval(muteTimer); muteTimer = null; } }

    return loadPage(page.url).then(function () {
      var win = frame.contentWindow;
      var doc = frame.contentDocument;
      if (!doc || !win) { throw new Error('cross_origin'); }
      pageDoc = doc;
      muteMedia(doc); // silence anything that autoplays immediately on load
      muteTimer = setInterval(function () { muteMedia(doc); }, 250);
      return settle(win, doc).then(function () {
        if (!win.axe) {
          var s = doc.createElement('script');
          s.textContent = src;
          (doc.head || doc.documentElement).appendChild(s);
        }
        if (!win.axe) { throw new Error('axe_inject_failed'); }
        var opts = { resultTypes: ['violations'] };
        if (cfg.axeTags && cfg.axeTags.length) {
          opts.runOnly = { type: 'tag', values: cfg.axeTags };
        }
        return win.axe.run(doc, opts);
      });
    }).then(function (result) {
      var violations = (result && result.violations) || [];
      // Fire-and-forget: send accessible-name candidates for the ARIA tab.
      if (cfg.ariaUrl && pageDoc) {
        var aria = collectAria(pageDoc, result, page.url);
        if (aria.length) { post(cfg.ariaUrl, { candidates: aria, root: cfg.root || 0 }).catch(function () {}); }
      }
      return post(cfg.ingestUrl, {
        scanStart: scanStart, pageId: page.id, title: page.title, url: page.url,
        violations: violations, root: cfg.root || 0
      }).then(function () { return violations.length; });
    }).then(function (n) {
      stopMuteLoop();
      return n;
    }, function (err) {
      stopMuteLoop(); // clear the interval even when the page failed
      throw err;
    });
  }

  // ── Resume across refreshes ────────────────────────────────────────────
  // The scan loop lives in the browser, so a page refresh would otherwise start
  // over. We persist the running scan (its scanStart + the set of pages already
  // scanned) in localStorage so a refresh can CONTINUE instead of restarting.
  var STORE_KEY = 'accessplus_fullscan_v1';

  function loadSession() {
    try {
      var raw = window.localStorage.getItem(STORE_KEY);
      var s = raw ? JSON.parse(raw) : null;
      if (s && typeof s.scanStart === 'number' && s.done && typeof s.done === 'object') { return s; }
    } catch (e) { /* private mode / disabled → no persistence */ }
    return null;
  }
  function saveSession(s) {
    try { window.localStorage.setItem(STORE_KEY, JSON.stringify(s)); } catch (e) { /* ignore */ }
  }
  function clearSession() {
    try { window.localStorage.removeItem(STORE_KEY); } catch (e) { /* ignore */ }
  }

  // Same-origin scan targets (cross-origin pages belong to another domain's BE).
  function sameOriginPages() {
    var pages = [];
    var crossOrigin = 0;
    cfg.pages.forEach(function (p) {
      try {
        if (new URL(p.url, window.location.href).origin === window.location.origin) { pages.push(p); }
        else { crossOrigin += 1; }
      } catch (e) { pages.push(p); }
    });
    return { pages: pages, crossOrigin: crossOrigin };
  }

  function countDone(pages, done) {
    var n = 0;
    for (var i = 0; i < pages.length; i++) { if (done[pages[i].url]) { n += 1; } }
    return n;
  }

  function frontendScan(session) {
    var res = sameOriginPages();
    var pages = res.pages;
    var crossOrigin = res.crossOrigin;
    var total = pages.length;

    if (!total) {
      setPhase('Frontend-Analyse: keine gleichorigen Seiten gefunden.');
      if (crossOrigin) { setNote(crossOrigin + ' Seite(n) liegen auf einer anderen Domain – bitte von deren eigenem Backend scannen.'); }
      clearSession();
      return Promise.resolve();
    }

    var scanStart = session.scanStart;      // reused across a resume → coverage stays consistent
    var done = session.done;                // { url: true } for pages already scanned
    var failures = [];

    return getAxe().then(function (src) {
      var i = 0;
      function next() {
        if (i >= total) {
          // Auto-resolve only when EVERY scannable page ended up scanned.
          var allDone = true;
          for (var k = 0; k < total; k++) { if (!done[pages[k].url]) { allDone = false; break; } }
          var cover = allDone && failures.length === 0;
          var notes = [];
          if (!cover) {
            var list = failures.slice(0, 5).map(function (f) { return f.url + ' (' + f.reason + ')'; }).join(' · ');
            if (failures.length) {
              notes.push(failures.length + ' Seite(n) nicht scannbar – ' + list + (failures.length > 5 ? ' …' : '') + ' (Timeout/Weiterleitung/X-Frame-Options)');
            }
          }
          if (crossOrigin) {
            notes.push(crossOrigin + ' Seite(n) auf anderer Domain übersprungen (nur von deren eigenem Backend scannbar)');
          }
          if (notes.length) { setNote('Hinweis: ' + notes.join('. ') + '. Nichts wurde automatisch als „behoben" markiert.'); }
          return post(cfg.ingestUrl, { scanStart: scanStart, finalize: true, cover: cover, root: cfg.root || 0 })
            .catch(function () {})
            .then(function () { clearSession(); });
        }

        var page = pages[i];
        if (done[page.url]) { i += 1; return next(); } // already scanned before a refresh

        var scanned = countDone(pages, done);
        setPhase('Frontend-Analyse: Seite ' + (scanned + 1) + '/' + total);
        setPct(50 + Math.round((scanned / total) * 50));

        return scanPage(page, src, scanStart).then(function () {
          done[page.url] = true;
          saveSession(session); // checkpoint after every page → refresh-safe
        }).catch(function (err) {
          failures.push({ url: page.url, reason: (err && err.message) || 'Fehler' });
        }).then(function () {
          i += 1;
          if (i < total && i % BATCH_SIZE === 0) {
            setPhase('Pause … (' + countDone(pages, done) + '/' + total + ' Seiten geprüft)');
            return wait(BATCH_PAUSE).then(next);
          }
          return next();
        });
      }
      return next();
    });
  }

  // Fresh full run: database analysis, then a new frontend sweep.
  function runFull() {
    var session = { scanStart: Math.floor(Date.now() / 1000), done: {}, startedTs: Date.now() };
    clearSession();
    saveSession(session);

    btn.disabled = true;
    showBar();
    setNote('');
    setPct(3);
    setPhase('Datenbank-Analyse läuft …');

    post(cfg.dbUrl, { root: cfg.root || 0 })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (body) {
          return { status: r.status, body: body || {} };
        });
      })
      .then(function (res) {
        if (res.status !== 200 || res.body.ok !== true) {
          setNote('Datenbank-Analyse nicht gespeichert — HTTP ' + res.status
            + (res.body.reason ? ' (' + res.body.reason + ')' : (res.body.error ? ' (' + res.body.error + ')' : ''))
            + '. Nutze ggf. „Nur Datenbank-Analyse".');
        }
        setPct(50);
        setPhase('Frontend-Analyse startet …');
        return frontendScan(session);
      })
      .then(finishAndReload)
      .catch(scanFailed);
  }

  // Resume an interrupted frontend sweep (skip the DB step, keep the same
  // scanStart, only scan pages not yet done).
  function resumeScan(session) {
    btn.disabled = true;
    showBar();
    setNote('');
    setPct(50);
    setPhase('Frontend-Analyse wird fortgesetzt …');
    getAxe(); // warm cache
    frontendScan(session).then(finishAndReload).catch(scanFailed);
  }

  function finishAndReload() {
    setPct(100);
    setPhase('Fertig. Ergebnisse werden geladen …');
    setTimeout(function () { window.location.reload(); }, 900);
  }
  function scanFailed() {
    setPhase('Fehler beim Scan. Bitte erneut versuchen.');
    btn.disabled = false;
  }

  // ── Wire up the button, offering resume when an unfinished scan exists ──
  if (btn) {
    var session = loadSession();
    var res = sameOriginPages();
    var total = res.pages.length;
    var doneN = session ? countDone(res.pages, session.done) : 0;

    if (session && total > 0 && doneN < total) {
      // Unfinished scan detected → make the button continue, and offer a reset.
      btn.textContent = 'Scan fortsetzen (' + doneN + '/' + total + ')';
      setNote('Ein unterbrochener Frontend-Scan wurde gefunden (' + doneN + '/' + total + ' Seiten geprüft). '
        + '„Scan fortsetzen" macht dort weiter. ');
      btn.addEventListener('click', function () { resumeScan(loadSession() || session); });

      var reset = document.createElement('button');
      reset.type = 'button';
      reset.className = 'tl_submit';
      reset.style.marginLeft = '8px';
      reset.textContent = 'Von vorn starten';
      reset.addEventListener('click', function () { clearSession(); runFull(); });
      if (btn.parentNode) { btn.parentNode.insertBefore(reset, btn.nextSibling); }
    } else {
      if (session) { clearSession(); } // stale/complete session → drop it
      btn.addEventListener('click', runFull);
    }
  }
})();
