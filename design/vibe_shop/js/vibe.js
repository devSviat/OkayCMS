/**
 * vibe_shop shared behaviour.
 * Loaded after okay.js so every okay.js handler is already bound.
 * Nothing here replaces okay.js logic - it only adds the overlay ("sheet")
 * primitive, keyboard escapes and the touch fallback for hover-only menus.
 */
(function () {
    'use strict';

    var lastFocused = null;
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';

    function trapFocus(sheet, event) {
        var focusables = sheet.querySelectorAll(FOCUSABLE);
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function backdropFor(sheet) {
        var id = sheet.getAttribute('data-vs-backdrop');
        return id ? document.getElementById(id) : document.querySelector('.vs-sheet__backdrop');
    }

    window.vibeSheet = {
        open: function (sheet) {
            if (!sheet) return;
            lastFocused = document.activeElement;
            sheet.classList.add('is-open');
            sheet.removeAttribute('aria-hidden');
            var backdrop = backdropFor(sheet);
            if (backdrop) backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            var focusable = sheet.querySelector(FOCUSABLE);
            if (focusable) focusable.focus();
        },
        close: function (sheet) {
            if (!sheet) return;
            sheet.classList.remove('is-open');
            var backdrop = backdropFor(sheet);
            if (backdrop) backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
            if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
            lastFocused = null;
        }
    };

    document.addEventListener('click', function (event) {
        var backdrop = event.target.closest ? event.target.closest('.vs-sheet__backdrop') : null;
        if (backdrop) {
            var openSheet = document.querySelector('.vs-sheet.is-open');
            if (openSheet) window.vibeSheet.close(openSheet);
            return;
        }
        var closer = event.target.closest ? event.target.closest('[data-vs-sheet-close]') : null;
        if (closer) {
            window.vibeSheet.close(closer.closest('.vs-sheet'));
        }
    });

    /* Disclosure: language / currency switchers and any click-driven dropdown. */
    function closeDisclosures(except) {
        var open = document.querySelectorAll('.vs-disclosure.is-open');
        for (var i = 0; i < open.length; i++) {
            if (open[i] === except) continue;
            open[i].classList.remove('is-open');
            var trigger = open[i].querySelector('.vs-disclosure__trigger');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        var trigger = event.target.closest('.vs-disclosure__trigger');
        if (trigger) {
            var host = trigger.closest('.vs-disclosure');
            var willOpen = !host.classList.contains('is-open');
            closeDisclosures(host);
            host.classList.toggle('is-open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            event.preventDefault();
            return;
        }
        if (!event.target.closest('.vs-disclosure')) closeDisclosures(null);
    });

    /* The catalogue drop-down is opened by okay.js and has no outside-click
       close of its own; clicking the trigger again is what closes it.
       okay.js also only toggles a class, so aria-expanded is mirrored here. */
    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        var catalog = document.querySelector('.vs-catalog');
        var catalogTrigger = document.querySelector('.vs-catalog-btn');
        if (!catalog || !catalogTrigger) return;
        if (event.target.closest('.vs-catalog-btn')) {
            catalogTrigger.setAttribute('aria-expanded', catalogTrigger.classList.contains('active') ? 'true' : 'false');
            return;
        }
        if (catalog.offsetParent === null) return;
        if (event.target.closest('.vs-catalog')) return;
        catalogTrigger.click();
        catalogTrigger.setAttribute('aria-expanded', 'false');
    });

    /* Touch fallback: on devices without hover, the first tap on a parent
       item opens its submenu instead of following the link. */
    var noHover = window.matchMedia ? window.matchMedia('(hover: none)') : null;

    document.addEventListener('click', function (event) {
        if (!noHover || !noHover.matches || !event.target.closest) return;
        var link = event.target.closest('.vs-has-children > a');
        if (!link) return;
        var item = link.parentNode;
        if (item.classList.contains('is-open')) return;
        event.preventDefault();
        var siblings = item.parentNode.children;
        for (var i = 0; i < siblings.length; i++) {
            if (siblings[i] !== item) siblings[i].classList.remove('is-open');
        }
        item.classList.add('is-open');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.key !== 'Esc' && event.key !== 'Tab') return;

        var sheet = document.querySelector('.vs-sheet.is-open');
        if (sheet) {
            if (event.key === 'Tab') {
                trapFocus(sheet, event);
            } else {
                window.vibeSheet.close(sheet);
            }
            return;
        }

        if (event.key === 'Tab') return;

        /* Off-canvas mobile navigation (hc-offcanvas-nav, driven by okay.js
           config in scripts.tpl) has no Escape handler of its own. */
        if (document.body.classList.contains('hc-nav-open')) {
            var toggle = document.querySelector('.hc-nav-trigger');
            if (toggle) {
                toggle.click();
                return;
            }
        }

        var catalog = document.querySelector('.vs-catalog');
        var catalogTrigger = document.querySelector('.vs-catalog-btn');
        if (catalog && catalogTrigger && catalog.offsetParent !== null) {
            catalogTrigger.click();
            catalogTrigger.focus();
            return;
        }

        closeDisclosures(null);
    });
}());
