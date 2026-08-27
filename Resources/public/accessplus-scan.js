/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * vtinnovations/accessplus — frontend axe scanner orchestrator.
 *
 * Runs entirely in the backend user's browser. For each published page it loads
 * the frontend URL in a same-origin iframe, injects the bundled axe-core, runs
 * axe.run(), and POSTs the violations to the ingest route. No third-party script
 * is loaded remotely — axe-core is served from this bundle's own assets.
 */
(function () {
  'use strict';

  var cfg = window.VTA11Y_SCAN;
  if (!cfg) {
    return;
  }

  var btn = document.getElementById('accessplus-scan-start');
  var prog = document.getElementById('accessplus-scan-progress');
  var frame = document.getElementById('accessplus-scan-frame');
  var axeSource = null;

  function log(msg) {
    if (prog) {
      prog.textContent = msg;
    }
  }

  function getAxe() {
    if (axeSource) {
      return Promise.resolve(axeSource);
    }
    return fetch(cfg.axeUrl).then(function (r) {
      return r.text();
    }).then(function (src) {
      axeSource = src;
      return src;
    });
  }

  function loadPage(url) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () {
        reject(new Error('timeout'));
      }, 30000);
      frame.onload = function () {
        clearTimeout(timer);
        resolve();
      };
      frame.onerror = function () {
        clearTimeout(timer);
        reject(new Error('load_error'));
      };
      frame.src = url;
    });
  }

  function scanPage(page, src, scanStart) {
    return loadPage(page.url).then(function () {
      var win = frame.contentWindow;
      var doc = frame.contentDocument;
      if (!doc || !win) {
        throw new Error('cross_origin');
      }
      if (!win.axe) {
        var s = doc.createElement('script');
        s.textContent = src;
        (doc.head || doc.documentElement).appendChild(s);
      }
      if (!win.axe) {
        throw new Error('axe_inject_failed');
      }
      var opts = { resultTypes: ['violations'] };
      // Run ONLY the legally mandatory rule tags (WCAG A/AA = BITV/BFSG).
      if (cfg.axeTags && cfg.axeTags.length) {
        opts.runOnly = { type: 'tag', values: cfg.axeTags };
      }
      return win.axe.run(doc, opts);
    }).then(function (result) {
      var violations = (result && result.violations) || [];
      return fetch(cfg.ingestUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Contao-Request-Token': cfg.token
        },
        body: JSON.stringify({ scanStart: scanStart, pageId: page.id, title: page.title, url: page.url, violations: violations })
      }).then(function () {
        return violations.length;
      });
    });
  }

  function finalize(scanStart) {
    return fetch(cfg.ingestUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Contao-Request-Token': cfg.token
      },
      body: JSON.stringify({ scanStart: scanStart, finalize: true })
    });
  }

  function run() {
    btn.disabled = true;
    var total = cfg.pages.length;
    var done = 0;
    var issues = 0;
    // Session marker: findings not refreshed past this point get auto-resolved.
    var scanStart = Math.floor(Date.now() / 1000);

    getAxe().then(function (src) {
      function next() {
        if (done >= total) {
          finalize(scanStart).catch(function () {}).then(function () {
            log('Fertig. ' + total + ' Seiten gescannt. Befunde zusammengefasst im Bericht/Dashboard.');
            btn.disabled = false;
          });
          return;
        }
        var page = cfg.pages[done];
        log('Scanne ' + (done + 1) + '/' + total + ': ' + page.url);
        scanPage(page, src, scanStart).then(function (n) {
          issues += n;
        }).catch(function () {
          // skip this page, keep going
        }).then(function () {
          done += 1;
          next();
        });
      }
      next();
    }).catch(function () {
      log('Fehler: axe-core konnte nicht geladen werden.');
      btn.disabled = false;
    });
  }

  if (btn) {
    btn.addEventListener('click', run);
  }
})();
