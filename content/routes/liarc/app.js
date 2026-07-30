(function () {
    'use strict';

    var body = document.body;
    var authed = body.dataset.authed === '1';
    var csrf = body.dataset.csrf || '';
    var page = body.dataset.page || 'index';

    var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    var KEY = 'liarc_device';

    function readDevice() {
        try {
            var d = JSON.parse(localStorage.getItem(KEY) || 'null');
            return (d && d.token && d.username) ? d : null;
        } catch (e) { return null; }
    }

    function clearDevice() {
        try { localStorage.removeItem(KEY); } catch (e) {}
    }

    function deviceName() {
        var ua = navigator.userAgent;
        var os = /iPhone|iPad|iPod/i.test(ua) ? 'iOS'
            : /Android/i.test(ua) ? 'Android'
            : /Windows/i.test(ua) ? 'Windows'
            : /Mac/i.test(ua) ? 'macOS'
            : /Linux/i.test(ua) ? 'Linux' : 'Web';
        return os + (isStandalone ? ' App' : ' Browser');
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm('?')) e.preventDefault();
        });
    });

    document.querySelectorAll('form[action*="v=logout"]').forEach(function (f) {
        f.addEventListener('submit', clearDevice);
    });

    var openNew = document.querySelector('[data-open-new]');
    if (openNew) {
        openNew.addEventListener('click', function () {
            var d = document.getElementById('new');
            if (d) d.open = true;
        });
    }

    var kindSelect = document.querySelector('[data-kind-select]');
    if (kindSelect) {
        var syncKind = function () {
            var series = kindSelect.value === 'series';
            document.querySelectorAll('[data-kind-series]').forEach(function (el) {
                el.classList.toggle('hidden', !series);
            });
            document.querySelectorAll('[data-kind-records]').forEach(function (el) {
                el.classList.toggle('hidden', series);
            });
        };
        kindSelect.addEventListener('change', syncKind);
        syncKind();
    }

    if (page === 'install') {
        var block = /iPhone|iPad|iPod/i.test(navigator.userAgent) ? 'ios'
            : /Android/i.test(navigator.userAgent) ? 'android' : 'other';
        var el = document.querySelector('[data-install-' + block + ']');
        if (el) el.classList.remove('hidden');
    }

    // Handy im Browser: App nur als Home-Webapp, sonst Anleitung
    var openPages = ['login', 'register', 'install'];
    if (authed && isMobile && !isStandalone && openPages.indexOf(page) === -1) {
        location.replace('?_page=auth&v=install');
        return;
    }

    // gespeicherter Geraeteschluessel meldet ohne Passwort an
    if (!authed && page === 'login') {
        var dev = readDevice();
        if (dev) {
            var hint = document.querySelector('[data-device-login]');
            if (hint) hint.classList.remove('hidden');
            fetch('?_page=api&p=auth/device', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: dev.username, token: dev.token })
            }).then(function (r) {
                if (r.ok) { location.replace('?_page=index'); }
                else { clearDevice(); if (hint) hint.classList.add('hidden'); }
            }).catch(function () { if (hint) hint.classList.add('hidden'); });
        }
    }

    // nach Login: Geraeteschluessel anlegen und lokal speichern
    if (authed && !readDevice() && csrf) {
        var params = new URLSearchParams();
        params.set('csrf', csrf);
        params.set('name', deviceName());
        fetch('?_page=data&do=provision', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            if (d && d.ok && d.token) {
                try {
                    localStorage.setItem(KEY, JSON.stringify({ username: d.username, token: d.token }));
                } catch (e) {}
            }
        }).catch(function () {});
    }

    var chartEl = document.querySelector('[data-chart]');
    var chartData = document.querySelector('[data-chart-data]');
    if (chartEl && chartData) {
        var points = [];
        try { points = JSON.parse(chartData.textContent) || []; } catch (e) {}
        if (points.length > 0) renderChart(chartEl, points);
    }

    function renderChart(el, points) {
        var W = 720, H = 220, padL = 44, padR = 12, padT = 10, padB = 26;
        var xs = points.map(function (p) { return p.at; });
        var ys = points.map(function (p) { return p.value; });
        var xMin = Math.min.apply(null, xs), xMax = Math.max.apply(null, xs);
        var yMin = Math.min.apply(null, ys), yMax = Math.max.apply(null, ys);
        if (xMax === xMin) xMax += 1;
        var ySpan = yMax - yMin;
        yMin -= ySpan === 0 ? 1 : ySpan * 0.1;
        yMax += ySpan === 0 ? 1 : ySpan * 0.1;

        function X(v) { return padL + (v - xMin) / (xMax - xMin) * (W - padL - padR); }
        function Y(v) { return H - padB - (v - yMin) / (yMax - yMin) * (H - padT - padB); }

        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);

        function line(x1, y1, x2, y2) {
            var l = document.createElementNS(ns, 'line');
            l.setAttribute('x1', x1); l.setAttribute('y1', y1);
            l.setAttribute('x2', x2); l.setAttribute('y2', y2);
            l.setAttribute('stroke', '#2a2a34'); l.setAttribute('stroke-width', '1');
            svg.appendChild(l);
        }

        function text(x, y, str, anchor) {
            var t = document.createElementNS(ns, 'text');
            t.setAttribute('x', x); t.setAttribute('y', y);
            t.setAttribute('fill', '#85858f'); t.setAttribute('font-size', '11');
            t.setAttribute('text-anchor', anchor || 'start');
            t.textContent = str;
            svg.appendChild(t);
        }

        for (var i = 0; i <= 4; i++) {
            var yv = yMin + (yMax - yMin) * i / 4;
            line(padL, Y(yv), W - padR, Y(yv));
            text(padL - 6, Y(yv) + 4, (Math.round(yv * 10) / 10).toString(), 'end');
        }

        var xTicks = Math.min(4, points.length - 1);
        for (var j = 0; j <= xTicks; j++) {
            var xv = xMin + (xMax - xMin) * (xTicks === 0 ? 0 : j / xTicks);
            text(X(xv), H - 8, new Date(xv * 1000).toISOString().slice(0, 10),
                j === 0 ? 'start' : (j === xTicks ? 'end' : 'middle'));
        }

        var poly = document.createElementNS(ns, 'polyline');
        poly.setAttribute('points', points.map(function (p) {
            return X(p.at) + ',' + Y(p.value);
        }).join(' '));
        poly.setAttribute('fill', 'none');
        poly.setAttribute('stroke', '#6ea8fe');
        poly.setAttribute('stroke-width', '2');
        svg.appendChild(poly);

        points.forEach(function (p) {
            var c = document.createElementNS(ns, 'circle');
            c.setAttribute('cx', X(p.at)); c.setAttribute('cy', Y(p.value));
            c.setAttribute('r', '3'); c.setAttribute('fill', '#6ea8fe');
            svg.appendChild(c);
        });

        el.appendChild(svg);
    }
})();
