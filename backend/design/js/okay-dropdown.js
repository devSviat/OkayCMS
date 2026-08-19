/**
 * Відкриває й закриває випадайки: [data-toggle="dropdown"] перемикає клас open
 * на своєму батькові й шле йому show/shown/hide/hidden.bs.dropdown.
 *
 * Єдиний споживач в адмінці - bootstrap-select: розмітку кнопки й меню малює
 * він, а клас open чекає ззовні. Тому змінювати тут імена класу й подій не
 * можна - плагін слухає саме їх.
 *
 * Клік по полю введення всередині відкритого меню меню не закриває: там живе
 * пошук по списку.
 */
(function ($) {
    'use strict';

    var TOGGLE = '[data-toggle="dropdown"]';
    var OPEN = 'open';

    function parentOf(toggle) {
        var selector = toggle.getAttribute('data-target') || toggle.getAttribute('href');
        var target = selector && /#[A-Za-z]/.test(selector) ? document.querySelector(selector) : null;
        return $(target || toggle.parentNode);
    }

    function closeAll(event) {
        $(TOGGLE).each(function () {
            var $parent = parentOf(this);
            if (!$parent.hasClass(OPEN)) {
                return;
            }
            /* Поле введення всередині меню - це пошук по списку: клік по ньому
               меню не закриває. */
            if (event && event.type === 'click' && /input|textarea/i.test(event.target.tagName)
                && $.contains($parent[0], event.target)) {
                return;
            }

            var hide = $.Event('hide.bs.dropdown', {relatedTarget: this});
            $parent.trigger(hide);
            if (hide.isDefaultPrevented()) {
                return;
            }
            this.setAttribute('aria-expanded', 'false');
            $parent.removeClass(OPEN).trigger($.Event('hidden.bs.dropdown', {relatedTarget: this}));
        });
    }

    function toggle(e) {
        var $this = $(this);
        if ($this.is('.disabled, :disabled')) {
            return;
        }

        var $parent = parentOf(this);
        var isActive = $parent.hasClass(OPEN);

        closeAll();
        if (isActive) {
            return false;
        }

        var show = $.Event('show.bs.dropdown', {relatedTarget: this});
        $parent.trigger(show);
        if (show.isDefaultPrevented()) {
            return;
        }

        $this.trigger('focus').attr('aria-expanded', 'true');
        $parent.addClass(OPEN).trigger($.Event('shown.bs.dropdown', {relatedTarget: this}));

        return false;
    }

    function keydown(e) {
        if (!/(38|40|27|32)/.test(e.which) || /input|textarea/i.test(e.target.tagName)) {
            return;
        }

        var $this = $(this);
        e.preventDefault();
        e.stopPropagation();

        if ($this.is('.disabled, :disabled')) {
            return;
        }

        var $parent = parentOf(this);
        var isActive = $parent.hasClass(OPEN);

        if (!isActive && e.which !== 27 || isActive && e.which === 27) {
            if (e.which === 27) {
                $parent.find(TOGGLE).trigger('focus');
            }
            $this.trigger('click');
            return;
        }

        var $items = $parent.find('.dropdown-menu li:not(.disabled):visible a');
        if (!$items.length) {
            return;
        }

        var index = $items.index(e.target);
        if (e.which === 38 && index > 0) {
            index--;
        }
        if (e.which === 40 && index < $items.length - 1) {
            index++;
        }
        $items.eq(~index ? index : 0).trigger('focus');
    }

    $(document)
        .on('click.okay.dropdown', closeAll)
        .on('click.okay.dropdown', TOGGLE, toggle)
        .on('keydown.okay.dropdown', TOGGLE + ', [role="listbox"]', keydown);
})(jQuery);
