/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * vtinnovations/accessplus — runtime accessible-name applier.
 *
 * Reads the approved selector→value map (emitted as a JSON <script> block by
 * AriaInjector) and sets aria-label on each matching element that is still
 * unnamed. Never overrides an existing accessible name (aria-label,
 * aria-labelledby, title, or visible text). No eval, no innerHTML — setAttribute
 * only. A selector that matches nothing is simply skipped.
 */
(function () {
  'use strict';

  function parseData() {
    var el = document.getElementById('accessplus-aria-data');
    if (!el) { return []; }
    try {
      var data = JSON.parse(el.textContent || '[]');
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  // True when the element already conveys an accessible name we must not touch.
  function alreadyNamed(node, attr) {
    if (node.getAttribute(attr)) { return true; }
    if (node.getAttribute('aria-labelledby')) { return true; }
    if (attr !== 'title' && node.getAttribute('title')) { return true; }
    var tag = (node.tagName || '').toLowerCase();
    // For links/buttons, non-empty visible text is already a name.
    if ((tag === 'a' || tag === 'button') && (node.textContent || '').trim() !== '') {
      return true;
    }
    return false;
  }

  function apply() {
    var map = parseData();
    for (var i = 0; i < map.length; i++) {
      var item = map[i];
      if (!item || !item.s || !item.v) { continue; }
      var attr = item.a || 'aria-label';
      var nodes;
      try {
        nodes = document.querySelectorAll(item.s);
      } catch (e) {
        continue; // invalid selector → skip
      }
      for (var j = 0; j < nodes.length; j++) {
        try {
          if (!alreadyNamed(nodes[j], attr)) {
            nodes[j].setAttribute(attr, item.v);
          }
        } catch (e) { /* ignore individual element errors */ }
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', apply);
  } else {
    apply();
  }
})();
