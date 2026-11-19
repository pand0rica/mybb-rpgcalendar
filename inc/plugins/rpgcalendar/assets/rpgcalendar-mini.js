/* rpgcalendar – index mini calendar: day popover + collapse toggle (no dependencies). */
(function () {
    'use strict';

    var root = document.getElementById('rpgcalendar_mini');
    if (!root) {
        return;
    }

    var STORE_KEY = 'rpgcalendar_mini_collapsed';
    var body = root.querySelector('.rpgcalendar_mini_body');
    var toggle = root.querySelector('.rpgcalendar_mini_toggle');
    var pop = root.querySelector('.rpgcalendar_mini_popover');
    var openBtn = null;

    function readStore() {
        try { return window.localStorage.getItem(STORE_KEY) === '1'; } catch (e) { return false; }
    }
    function writeStore(v) {
        try { window.localStorage.setItem(STORE_KEY, v ? '1' : '0'); } catch (e) { /* storage unavailable */ }
    }

    function setCollapsed(collapsed) {
        root.classList.toggle('rpgcalendar_mini--collapsed', collapsed);
        if (body) { body.hidden = collapsed; }
        if (toggle) { toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true'); }
        if (collapsed) { closePop(); }
    }

    function closePop() {
        pop.hidden = true;
        pop.innerHTML = '';
        if (openBtn) { openBtn.classList.remove('rpgcalendar_mini_daybtn--open'); }
        openBtn = null;
    }

    function safeUrl(u) {
        // only relative or http(s) links (the server validated them already)
        return (typeof u === 'string' && (u === '' || /^(https?:\/\/|[a-z0-9_.\-\/]+(\?|$))/i.test(u))) ? u : '';
    }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    }

    function openPop(btn, info) {
        closePop();
        pop.appendChild(el('div', 'rpgcalendar_mini_pop_date', info.date));
        if (info.moon) {
            pop.appendChild(el('div', 'rpgcalendar_mini_pop_moon', info.moon));
        }
        var list = el('ul', 'rpgcalendar_mini_pop_list');
        (info.items || []).forEach(function (item) {
            var li = el('li', 'rpgcalendar_mini_pop_item rpgcalendar_mini_pop_item--' + String(item.type).replace(/[^a-z]/g, ''));
            li.appendChild(el('span', 'rpgcalendar_mini_pop_type', item.label || ''));
            var url = safeUrl(item.url) || safeUrl(info.day);
            if (url) {
                var a = el('a', '', item.title);
                a.href = url;
                li.appendChild(a);
            } else {
                li.appendChild(el('span', '', item.title));
            }
            list.appendChild(li);
        });
        if (list.childNodes.length) { pop.appendChild(list); }
        var dayUrl = safeUrl(info.day);
        if (dayUrl) {
            var more = el('a', 'rpgcalendar_mini_pop_more', '→');
            more.href = dayUrl;
            pop.appendChild(more);
        }
        pop.hidden = false;

        // position below the clicked day, kept inside the widget
        var rootBox = root.getBoundingClientRect();
        var btnBox = btn.getBoundingClientRect();
        var left = btnBox.left - rootBox.left;
        var maxLeft = Math.max(0, rootBox.width - pop.offsetWidth);
        pop.style.left = Math.min(Math.max(0, left), maxLeft) + 'px';
        pop.style.top = (btnBox.bottom - rootBox.top + 4) + 'px';

        btn.classList.add('rpgcalendar_mini_daybtn--open');
        openBtn = btn;
    }

    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.rpgcalendar_mini_daybtn') : null;
        if (btn && root.contains(btn)) {
            var raw = btn.getAttribute('data-info');
            if (!raw) { return; }
            if (openBtn === btn) { closePop(); return; }
            try { openPop(btn, JSON.parse(raw)); } catch (e) { closePop(); }
            return;
        }
        if (ev.target.closest && ev.target.closest('.rpgcalendar_mini_popover')) { return; }
        closePop();
    });

    document.addEventListener('click', function (ev) {
        if (!root.contains(ev.target)) { closePop(); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closePop(); }
    });

    if (toggle) {
        toggle.addEventListener('click', function () {
            var collapsed = !root.classList.contains('rpgcalendar_mini--collapsed');
            setCollapsed(collapsed);
            writeStore(collapsed);
        });
    }

    setCollapsed(readStore());
})();
