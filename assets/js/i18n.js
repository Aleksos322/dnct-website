/* Wersja językowa EN/DE po stronie przeglądarki (backend/lib/i18n.php).
 *
 * Serwer tłumaczy gotowy HTML, ale część tekstów powstaje dopiero w JS
 * (konfigurator slotów, czat w paśmie Discorda, makieta panelu) - tu
 * dokładamy to samo tłumaczenie tym samym słownikiem (window.DNCT_I18N).
 *
 * Ceny: "6,99 zł" w jednym tekście przelicza już serwer. Tu łapiemy ceny
 * rozbite na dwa elementy - liczba w jednym, "zł" w drugim (karty planów,
 * konfigurator, pasek "Twój wybór") - i liczby, które JS podmienia później.
 * Tylko wyświetlanie: płatność dalej idzie w złotych.
 */
(function () {
    'use strict';
    var C = window.DNCT_I18N;
    if (!C || C.lang === 'pl') return;

    var S = C.slownik || {};
    var W = (C.wzorce || []).map(function (w) { return [new RegExp(w[0], 'u'), w[1]]; });
    var DE = C.lang === 'de';
    // Liczba przed "zł": przecinek z PHP ("64,99") albo kropka z cennik.js
    // ("109.99" - money() formatuje z kropką). Bez kropki konfigurator
    // pokazywał kwotę w złotych z dopiskiem €.
    var NUM = /^\s*(\d{1,3}(?:[ \u00a0]?\d{3})*(?:[.,]\d{1,2})?)\s*$/;
    var napisane = new WeakMap();   // węzeł -> tekst, który sami wpisaliśmy

    function eur(pln) {
        var v = parseFloat(String(pln).replace(/[ \u00a0]/g, '').replace(',', '.')) || 0;
        var e = v <= 0 ? 0 : Math.max(0.99, Math.ceil(v / C.kurs) - 0.01);
        var s = e.toFixed(2);
        return DE ? s.replace('.', ',') : s;
    }
    function ceny(t) {
        return t.replace(/(\d{1,3}(?:[ \u00a0]?\d{3})*[.,]\d{2})(\s*)zł(\/mies\.)?/g, function (m, n, sp, mies) {
            return eur(n) + sp + '€' + (mies ? (DE ? '/Monat' : '/mo') : '');
        });
    }
    function tlumacz(t) {
        if (Object.prototype.hasOwnProperty.call(S, t)) return S[t];
        for (var i = 0; i < W.length; i++) {
            if (W[i][0].test(t)) {
                var r = t.replace(W[i][0], W[i][1]);
                return DE ? r.replace('pro tydzień', 'pro Woche').replace('pro rok', 'pro Jahr')
                          : r.replace('per tydzień', 'per week').replace('per rok', 'per year');
            }
        }
        return null;
    }

    // poprzedni węzeł tekstowy z treścią (przez granice elementów)
    function poprzedniTekst(node) {
        var w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        w.currentNode = node;
        var n, kroki = 0;
        while ((n = w.previousNode()) && kroki++ < 4) {
            if (n.nodeValue.trim() !== '') return n;
        }
        return null;
    }

    function wpisz(node, v) {
        if (node.nodeValue === v) return;
        node.nodeValue = v;
        napisane.set(node, v);
    }

    // Kwoty, które Stripe naprawdę pobiera (suma zamówienia, kwota na
    // potwierdzeniu), zostają w zł - obok kontener dostaje data-eur
    // z przybliżeniem w euro (CSS pokazuje je jako "≈ X €").
    function wPln(node) {
        var el = node.parentNode;
        return el && el.closest ? el.closest('[data-i18n-pln]') : null;
    }

    function zrobWezel(node) {
        var p = node.parentNode;
        if (!p || /^(SCRIPT|STYLE|TEXTAREA|PRE)$/.test(p.nodeName)) return;
        var pln = wPln(node);
        if (pln) {
            var tekst = node.nodeValue.replace(/\s+/g, ' ').trim();
            var m = /(\d{1,3}(?:[ \u00a0]?\d{3})*[.,]\d{2})/.exec(tekst);
            if (m) pln.setAttribute('data-eur', '≈ ' + eur(m[1]) + ' €');
            var trp = tekst.length >= 3 ? tlumacz(tekst) : null;
            if (trp !== null && trp !== tekst) wpisz(node, node.nodeValue.replace(tekst, trp));
            return;
        }
        var v = node.nodeValue;
        if (napisane.get(node) === v) return;
        var t = v.replace(/\s+/g, ' ').trim();
        if (t === '') return;
        // pojedyncze znaki z efektów pisania (nagłówek cennika, opinie) - bez
        // tego "i" wpisane litera po literze zamieniałoby się w "and"
        if (t.length < 3 && t !== 'zł' && !NUM.test(t)) return;

        // samotne "zł" za liczbą w poprzednim elemencie
        if (t === 'zł' || t === '€') {
            var prev = poprzedniTekst(node);
            if (prev && NUM.test(prev.nodeValue) && napisane.get(prev) !== prev.nodeValue) {
                wpisz(prev, prev.nodeValue.replace(NUM.exec(prev.nodeValue)[1], eur(NUM.exec(prev.nodeValue)[1])));
            }
            if (t === 'zł') wpisz(node, v.replace('zł', '€'));
            return;
        }
        // liczba podmieniona przez JS obok "€" (konfigurator)
        if (NUM.test(v)) {
            var w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            w.currentNode = node;
            var nast = w.nextNode();
            while (nast && nast.nodeValue.trim() === '') nast = w.nextNode();
            if (nast && /^\s*(zł|€)/.test(nast.nodeValue)) {
                wpisz(node, v.replace(NUM.exec(v)[1], eur(NUM.exec(v)[1])));
                if (/^\s*zł/.test(nast.nodeValue)) wpisz(nast, nast.nodeValue.replace('zł', '€'));
            }
            return;
        }
        var klucz = p.getAttribute && p.getAttribute('data-i18n');
        var tr = klucz ? tlumacz(klucz) : tlumacz(t);
        var wynik = ceny(tr !== null ? tr : t);
        if (wynik !== t) wpisz(node, v.replace(t, wynik));
    }

    function przejdz(root) {
        if (root.nodeType === 3) { zrobWezel(root); return; }
        if (root.nodeType !== 1) return;
        var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), n, lista = [];
        while ((n = w.nextNode())) lista.push(n);
        lista.forEach(zrobWezel);
        ['title', 'aria-label', 'placeholder'].forEach(function (a) {
            var els = root.querySelectorAll ? root.querySelectorAll('[' + a + ']') : [];
            Array.prototype.forEach.call(els, function (el) {
                var t = el.getAttribute(a), tr = tlumacz(t);
                if (tr !== null) el.setAttribute(a, tr);
            });
        });
    }

    // Dla skryptów, które składają tekst z kawałków (success.js).
    window.DNCT_T = function (t) { var r = tlumacz(t); return r === null ? t : r; };

    przejdz(document.body);

    new MutationObserver(function (lista) {
        lista.forEach(function (m) {
            if (m.type === 'characterData') zrobWezel(m.target);
            else m.addedNodes.forEach(przejdz);
        });
    }).observe(document.body, { childList: true, subtree: true, characterData: true });
})();
