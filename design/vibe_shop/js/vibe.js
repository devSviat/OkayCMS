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

    /* Any control pointed at the sheet with aria-controls announces its state,
       whichever of the close paths - button, backdrop or Escape - was used. */
    function announce(sheet, open) {
        if (!sheet.id) return;
        var triggers = document.querySelectorAll('[aria-controls="' + sheet.id + '"]');
        for (var i = 0; i < triggers.length; i++) {
            triggers[i].setAttribute('aria-expanded', open ? 'true' : 'false');
        }
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
            announce(sheet, true);
            var focusable = sheet.querySelector(FOCUSABLE);
            if (focusable) focusable.focus();
        },
        close: function (sheet) {
            if (!sheet) return;
            sheet.classList.remove('is-open');
            var backdrop = backdropFor(sheet);
            if (backdrop) backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
            announce(sheet, false);
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
       percent instead. Applies to any host whose .fn_discount_label is one of
       our badges - the card and the product page gallery both are; a theme that
       still renders okay.js's own .sticker markup is left alone. */
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

    /* ------------------------------------------------------- product page */

    /* The product page keeps the same three-state availability line as the
       card, but here both states are stated: okay.js swaps hidden-xs-up between
       .fn_in_stock and .fn_not_stock, so "out of stock" has its own visible
       line and only the amber "low stock" grade is left to add. */
    function syncPdp(select) {
        var pdp = select.closest ? select.closest('.vs-pdp') : null;
        if (!pdp) return;
        var option = select.options[select.selectedIndex];
        var stock = option ? parseInt(option.getAttribute('data-stock'), 10) : NaN;

        var chips = pdp.querySelectorAll('.vs-chip');
        for (var i = 0; i < chips.length; i++) {
            var on = chips[i].getAttribute('data-variant-id') === select.value;
            chips[i].classList.toggle('vs-chip--selected', on);
            chips[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        var line = pdp.querySelector('.fn_in_stock.vs-stock');
        if (line) {
            var lowAt = parseInt(line.getAttribute('data-low-at'), 10);
            if (isNaN(lowAt)) lowAt = LOW_STOCK_FALLBACK;
            var low = !isNaN(stock) && stock > 0 && stock <= lowAt;
            line.classList.toggle('vs-stock--low', low);
            line.classList.toggle('vs-stock--in', !low);
            var label = line.querySelector('.vs-stock__label');
            var copy = line.getAttribute(low ? 'data-low' : 'data-in');
            if (label && copy) label.textContent = copy;
        }

        normaliseDiscount(pdp);
    }

    /* Registered after okay.js so its handler has already rewritten the card.
       select2 fires its change through jQuery, which never reaches a native
       listener, so jQuery is used whenever it is present. */
    function syncVariant(select) {
        syncCard(select);
        syncPdp(select);
    }

    if (window.jQuery) {
        window.jQuery(document).on('change', '.fn_variant', function () {
            syncVariant(this);
        });
    } else {
        document.addEventListener('change', function (event) {
            var select = event.target;
            if (!select || !select.classList || !select.classList.contains('fn_variant')) return;
            syncVariant(select);
        });
    }

    /* Quantity stepper. The <input name="amount"> is the value the cart posts
       and is never replaced - the buttons only write to it. okay.js binds its
       own handler to `.fn_product_amount span`, which real <button>s do not
       match, so the arithmetic is handed straight back to okay.js's
       amount_change: it clamps to data-max, keeps okay.amount in step for the
       variant-change handler and fires the change event the cart page's ajax
       listener is waiting for. A second clamp here would be a second, and
       eventually divergent, definition of the maximum. */
    function stepAmount(input, action) {
        if (window.jQuery && typeof window.amount_change === 'function') {
            window.amount_change(window.jQuery(input), action);
            return;
        }
        var max = parseFloat(input.getAttribute('data-max'));
        var current = parseFloat(input.value);
        if (isNaN(current)) current = 1;
        var next = current + (action === 'plus' ? 1 : -1);
        if (!isNaN(max)) next = Math.min(max, next);
        input.value = Math.max(1, next);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        var btn = event.target.closest('.vs-stepper__btn');
        if (!btn) return;
        var wrap = btn.closest('.fn_product_amount');
        var input = wrap ? wrap.querySelector('input[name="amount"]') : null;
        if (!input) return;
        var step = parseInt(btn.getAttribute('data-vs-step'), 10);
        if (isNaN(step) || step === 0) return;
        stepAmount(input, step > 0 ? 'plus' : 'minus');
    });

    /* Nothing in okay.js clamps a hand-typed quantity: amount_change has a
       "keyup" branch but no caller for it on this page, so a typed 999 would be
       posted as is and the order quietly cut back later. Clamped once, on
       change, and never re-dispatched - the value is only corrected. */
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.classList || !input.classList.contains('vs-stepper__input')) return;
        var max = parseFloat(input.getAttribute('data-max'));
        if (window.jQuery) {
            var live = window.jQuery(input).data('max');
            if (live !== null && live !== undefined) max = parseFloat(live);
        }
        var value = parseInt(input.value, 10);
        if (isNaN(value) || value < 1) value = 1;
        if (!isNaN(max) && max > 0 && value > max) value = max;
        if (String(value) !== input.value) input.value = String(value);
    });

    /* Sticky mobile buy bar. Revealed once the inline buy row leaves the
       viewport, hidden again when it comes back. The row is observed rather
       than the button inside it: okay.js hides the add-to-cart button with
       hidden-xs-up whenever the chosen variant is out of stock, and a
       display:none element never intersects anything - the bar would latch on
       and stay for the rest of the visit. */
    (function () {
        var inline = document.querySelector('.vs-buybox__submit');
        var bar = document.querySelector('.vs-sticky-buy');
        if (!inline || !bar || !('IntersectionObserver' in window)) return;
        var target = inline.closest('.vs-buybox__buy') || inline;

        new IntersectionObserver(function (entries) {
            bar.classList.toggle('is-visible', !entries[0].isIntersecting);
        }, { threshold: 0 }).observe(target);
    }());

    /* --------------------------------------------------------- catalogue */

    /* The filter panel is the shared .vs-sheet primitive - focus trap, scroll
       lock, Escape and focus restore all come from window.vibeSheet above.
       Only the trigger is bound here. Closing is handled by the generic
       [data-vs-sheet-close] and backdrop listeners. */
    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        if (!event.target.closest('.vs-filters__open')) return;
        window.vibeSheet.open(document.querySelector('.vs-filters.vs-sheet'));
    });

    /* Resizing past the rail breakpoint turns the sheet back into a static
       column. Leaving it "open" would keep the body scroll lock on with no
       visible overlay to explain it. */
    var railQuery = window.matchMedia ? window.matchMedia('(min-width: 992px)') : null;

    function closeFiltersOnRail() {
        if (!railQuery || !railQuery.matches) return;
        var open = document.querySelector('.vs-filters.vs-sheet.is-open');
        if (open) window.vibeSheet.close(open);
    }

    if (railQuery) {
        if (railQuery.addEventListener) {
            railQuery.addEventListener('change', closeFiltersOnRail);
        } else if (railQuery.addListener) {
            railQuery.addListener(closeFiltersOnRail);
        }
    }

    /* okay.js replaces the contents of #fn_products_content on every ajax
       filter round-trip, and marks the trip in flight by appending its own
       .fn_ajax_wait node to the same element. Both are observed here so the
       skeleton is switched on by a class of ours and the result count - which
       lives outside the replaced region - is refreshed from the strings the
       server rendered with the new markup. Nothing is hidden until this runs:
       the grid and the count are in the HTML and visible by default. */

    /* okay.js has no error branch: if the request is dropped its .fn_ajax_wait
       node is never removed, so the loading class would stay on for the rest of
       the session and the shopper would be left looking at a shimmer instead of
       the products that are still in the DOM underneath. The bail is generous -
       it must never fire on a slow request that is still alive - and it only
       takes the skeleton off; the stale results below it stay readable and the
       page still works through a normal link. */
    var LOADING_BAIL_MS = 10000;
    var loadingBail = null;

    function syncCatalogue() {
        var region = document.querySelector('.vs-catalogue__region');
        if (!region) return;

        var results = region.querySelector('.vs-catalogue__results');
        var loading = !!(results && results.querySelector('.fn_ajax_wait'));
        region.classList.toggle('is-loading', loading);

        if (loadingBail) {
            window.clearTimeout(loadingBail);
            loadingBail = null;
        }

        if (loading) {
            loadingBail = window.setTimeout(function () {
                loadingBail = null;
                region.classList.remove('is-loading');
            }, LOADING_BAIL_MS);
            return;
        }

        var state = region.querySelector('.vs-catalogue__state');
        if (!state) return;
        writeAll('.vs-results__value', state.getAttribute('data-vs-count'));
        writeAll('.vs-filters__apply_label', state.getAttribute('data-vs-apply'));
    }

    function writeAll(selector, text) {
        if (text === null) return;
        var nodes = document.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].textContent = text;
        }
    }

    var productsRegion = document.getElementById('fn_products_content');
    if (productsRegion && window.MutationObserver) {
        new MutationObserver(syncCatalogue).observe(productsRegion, { childList: true });
    }

    /* okay.js collapses a filter group by class only, so the state the group
       header announces is mirrored here. Scoped to headers that already carry
       the attribute, so no other .fn_switch consumer is touched. */
    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        var head = event.target.closest('.fn_switch');
        if (!head || !head.hasAttribute('aria-expanded')) return;
        window.setTimeout(function () {
            head.setAttribute('aria-expanded', head.classList.contains('active') ? 'false' : 'true');
        }, 0);
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
