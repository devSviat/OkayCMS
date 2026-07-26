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

    /* ------------------------------------------------------- product card */

    var LOW_STOCK_FALLBACK = 5;

    /* Availability line. okay.js only knows the two states it can express with
       hidden-xs-up on .fn_is_stock / .fn_not_preorder, so the three-state dot
       is maintained here. The copy comes from data-* on the element itself so
       it stays translated by the template, not hard-coded in JS. */
    function applyStock(card, stock) {
        var el = card.querySelector('.vs-stock');
        if (!el) return;
        var label = el.querySelector('.vs-stock__label');
        var lowAt = parseInt(el.getAttribute('data-low-at'), 10);
        if (isNaN(lowAt)) lowAt = LOW_STOCK_FALLBACK;

        el.classList.remove('vs-stock--in', 'vs-stock--low', 'vs-stock--out');

        /* NaN (a variant with no stock figure at all) deliberately falls through
           to "in stock" - that is how okay.js reads the same attribute, and the
           two must not disagree about the same variant. */
        if (stock < 1) {
            /* Out of stock is stated once, by the .vs-card__unavailable slot
               okay.js reveals; repeating it here would say it twice. */
            el.classList.add('vs-stock--out', 'hidden-xs-up');
            if (label) label.textContent = el.getAttribute('data-out') || '';
            return;
        }

        el.classList.remove('hidden-xs-up');
        if (stock <= lowAt) {
            el.classList.add('vs-stock--low');
            if (label) label.textContent = el.getAttribute('data-low') || '';
        } else {
            el.classList.add('vs-stock--in');
            if (label) label.textContent = el.getAttribute('data-in') || '';
        }
    }

    /* okay.js writes data-discount verbatim, and that attribute is a frozen
       contract carrying two decimals ("-33.79 %"). The badge shows whole
       percent instead. Only the card badge is touched: the product page keeps
       its own markup inside .fn_discount_label. */
    function normaliseDiscount(card) {
        var badge = card.querySelector('.fn_discount_label');
        if (!badge || !badge.classList.contains('vs-badge')) return;
        var match = badge.textContent.replace(/ /g, ' ').match(/-?\d+(?:[.,]\d+)?/);
        if (!match) return;
        var value = parseFloat(match[0].replace(',', '.'));
        if (isNaN(value)) return;
        badge.textContent = Math.round(value) + ' %';
    }

    function syncCard(select) {
        var card = select.closest ? select.closest('.vs-card') : null;
        if (!card) return;
        var option = select.options[select.selectedIndex];
        var chips = card.querySelectorAll('.vs-chip');
        for (var i = 0; i < chips.length; i++) {
            var on = chips[i].getAttribute('data-variant-id') === select.value;
            chips[i].classList.toggle('vs-chip--selected', on);
            chips[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        applyStock(card, option ? parseInt(option.getAttribute('data-stock'), 10) : NaN);
        normaliseDiscount(card);
    }

    /* A chip is a shortcut for the <select>, which stays the value the form
       submits. Dispatching a real change event is what makes okay.js's existing
       handler recalculate price, old price, SKU, stock and the discount badge. */
    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        var chip = event.target.closest('.vs-chip');
        if (!chip) return;
        var form = chip.closest('form');
        var select = form ? form.querySelector('select[name="variant"]') : null;
        if (!select) return;
        event.preventDefault();
        if (select.value === chip.getAttribute('data-variant-id')) return;
        select.value = chip.getAttribute('data-variant-id');
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });

    /* Registered after okay.js so its handler has already rewritten the card.
       select2 fires its change through jQuery, which never reaches a native
       listener, so jQuery is used whenever it is present. */
    if (window.jQuery) {
        window.jQuery(document).on('change', '.fn_variant', function () {
            syncCard(this);
        });
    } else {
        document.addEventListener('change', function (event) {
            var select = event.target;
            if (!select || !select.classList || !select.classList.contains('fn_variant')) return;
            syncCard(select);
        });
    }

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
