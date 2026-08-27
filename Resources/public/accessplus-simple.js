/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

/*
 * Plain/Easy-language switch (vtinnovations/accessplus).
 * Sets the accessplus_simple cookie (einfach|leicht|'') and reloads; the server
 * (SimpleLanguageRenderer) swaps approved drafts in place. Self-contained,
 * no external calls, no tracking. Renders a floating button + top badge,
 * enhances nav links ([data-accessplus-register]) and embeds into the comfort
 * overlay panel when present.
 */
(function () {
    'use strict';

    var LABELS = {
        '': 'Alltagssprache',
        einfach: 'Einfache Sprache',
        leicht: 'Leichte Sprache'
    };
    var NOTE = 'KI-Entwurf, von Menschen geprüft. Keine zertifizierte Leichte Sprache.';

    function init() {
        var root = document.getElementById('accessplus-simple-root');
        if (!root) {
            return;
        }

        var cookieName = root.getAttribute('data-cookie') || 'accessplus_simple';
        var registers = (root.getAttribute('data-registers') || '').split(',').filter(Boolean);
        var active = root.getAttribute('data-active') || '';
        var showButton = root.getAttribute('data-button') === '1';
        var showOverlay = root.getAttribute('data-overlay') === '1';

        function setRegister(reg) {
            var maxAge = reg ? 2592000 : 0;
            document.cookie = cookieName + '=' + encodeURIComponent(reg) + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
            window.location.reload();
        }

        // Options: Standard ('') first, then offered registers.
        var options = [''].concat(registers);

        if (active) {
            renderBadge(active, setRegister);
        }
        if (showButton) {
            renderFab(options, active, setRegister);
        }
        enhanceNavLinks(setRegister);
        if (showOverlay) {
            embedIntoOverlay(options, active, setRegister);
        }
    }

    function renderBadge(active, setRegister) {
        var bar = document.createElement('div');
        bar.className = 'accessplus-simple-badge';
        bar.setAttribute('role', 'status');
        bar.appendChild(document.createTextNode((LABELS[active] || active) + ' — ' + NOTE));

        var off = document.createElement('button');
        off.type = 'button';
        off.appendChild(document.createTextNode('Zur Alltagssprache'));
        off.addEventListener('click', function () { setRegister(''); });
        bar.appendChild(off);

        document.body.insertBefore(bar, document.body.firstChild);
    }

    function renderFab(options, active, setRegister) {
        var wrap = document.createElement('div');
        wrap.className = 'accessplus-simple-fab';
        wrap.setAttribute('aria-expanded', 'false');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'accessplus-simple-fab__btn';
        btn.setAttribute('aria-haspopup', 'true');
        btn.appendChild(document.createTextNode('Sprache: ' + (LABELS[active] || 'Alltagssprache')));

        var menu = document.createElement('div');
        menu.className = 'accessplus-simple-menu';
        menu.setAttribute('role', 'menu');

        options.forEach(function (reg) {
            var item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'menuitem');
            if (reg === active) {
                item.setAttribute('aria-current', 'true');
            }
            item.appendChild(document.createTextNode(LABELS[reg] || reg));
            item.addEventListener('click', function () { setRegister(reg); });
            menu.appendChild(item);
        });

        btn.addEventListener('click', function () {
            var open = wrap.getAttribute('aria-expanded') === 'true';
            wrap.setAttribute('aria-expanded', open ? 'false' : 'true');
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                wrap.setAttribute('aria-expanded', 'false');
            }
        });

        wrap.appendChild(btn);
        wrap.appendChild(menu);
        document.body.appendChild(wrap);
    }

    function enhanceNavLinks(setRegister) {
        var links = document.querySelectorAll('[data-accessplus-register]');
        Array.prototype.forEach.call(links, function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                setRegister(el.getAttribute('data-accessplus-register') || '');
            });
        });
    }

    function embedIntoOverlay(options, active, setRegister) {
        // The comfort overlay mounts its own panel asynchronously; retry briefly.
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            var panel = document.querySelector('#accessplus-overlay-root .accessplus-panel');
            if (!panel) {
                if (tries > 20) { clearInterval(timer); }
                return;
            }
            clearInterval(timer);
            if (panel.querySelector('.accessplus-simple-embed')) {
                return;
            }

            var box = document.createElement('div');
            box.className = 'accessplus-simple-embed';
            var h = document.createElement('h3');
            h.appendChild(document.createTextNode('Sprache'));
            box.appendChild(h);

            options.forEach(function (reg) {
                var b = document.createElement('button');
                b.type = 'button';
                if (reg === active) {
                    b.setAttribute('aria-current', 'true');
                }
                b.appendChild(document.createTextNode(LABELS[reg] || reg));
                b.addEventListener('click', function () { setRegister(reg); });
                box.appendChild(b);
            });

            panel.appendChild(box);
        }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
