/**
 * Модалки адмінки.
 *
 * Читає ті самі data-toggle="modal", data-target і data-dismiss="modal", що
 * читав Bootstrap, тож жоден шаблон і жоден модуль не правиться. Фокус, Esc
 * і повернення фокуса після закриття бере на себе a11y-dialog - раніше цього
 * не було зовсім: фокус лишався під модалкою, а Esc її не закривав.
 *
 * Тут же живе шим $.fancybox: у адмінці ця бібліотека жодного разу не
 * працювала лайтбоксом, лише відкривала свій же inline-блок або готовий HTML.
 */
(function ($, A11yDialog) {
    'use strict';

    if (!A11yDialog) {
        return;
    }

    var KEY = 'okayModal';
    var SHIM = 'fn_modal_shim';

    /* Bootstrap ще в бандлі заради випадайок bootstrap-select, і його
       data-api відкрив би ту саму модалку вдруге. */
    $(document).off('click.bs.modal.data-api');

    function dialogFor(el) {
        var dialog = $.data(el, KEY);
        if (dialog) {
            return dialog;
        }

        dialog = new A11yDialog(el);
        dialog.on('show', function () {
            el.classList.add('in');
            document.body.classList.add('modal-open');
        });
        dialog.on('hide', function () {
            el.classList.remove('in');
            if (!document.querySelector('.modal.in')) {
                document.body.classList.remove('modal-open');
            }
        });
        $.data(el, KEY, dialog);
        return dialog;
    }

    function open(el, event) {
        if (el) {
            dialogFor(el).show(event);
        }
    }

    function close(el) {
        if (el && $.data(el, KEY)) {
            $.data(el, KEY).hide();
        }
    }

    $(document).on('click', '[data-toggle="modal"]', function (e) {
        var target = $(this).attr('data-target') || $(this).attr('href') || '';
        if (target.charAt(0) !== '#') {
            return;
        }
        e.preventDefault();
        open(document.querySelector(target), e.originalEvent || e);
    });

    $(document).on('click', '[data-dismiss="modal"]', function (e) {
        e.preventDefault();
        close($(this).closest('.modal')[0]);
    });

    /* Клік повз діалог закривав модалку й до заміни драйвера - підкладка в
       адмінці схована, тож ловить його сама .modal на всю ширину вікна. */
    $(document).on('click', '.modal', function (e) {
        if (e.target === this) {
            close(this);
        }
    });

    /* ---- шим fancyBox ------------------------------------------------- */

    /* Inline-вміст живе у своєму місці сторінки: на час показу переносимо
       його в оболонку, а на закритті повертаємо назад. Інакше друге
       відкриття не знайде вузла. */
    function shell() {
        var el = document.createElement('div');
        el.className = 'modal fade ' + SHIM;
        el.setAttribute('role', 'dialog');
        el.innerHTML = '<div class="modal-dialog"><div class="modal-content">' +
            '<button type="button" class="modal_shim_close" data-dismiss="modal" aria-label="Close"></button>' +
            '<div class="modal-body"></div></div></div>';
        document.body.appendChild(el);
        return el;
    }

    function openShim(options) {
        var opts = options || {};
        var src = opts.src;
        var el = shell();
        var body = el.querySelector('.modal-body');
        var inline = null;
        var anchor = null;
        var hidden = null;

        if (typeof src === 'string' && src.charAt(0) === '#') {
            inline = document.querySelector(src);
            if (!inline) {
                el.remove();
                return null;
            }
            anchor = document.createComment('okay-modal:' + src.slice(1));
            inline.parentNode.insertBefore(anchor, inline);
            body.appendChild(inline);
            /* Вміст лежить на сторінці схованим - показати його має той, хто
               відкриває. fancyBox робив те саме, і без цього модалка відкриється
               порожньою. */
            hidden = inline.style.display;
            inline.style.display = '';
        } else if (opts.type === 'html' && typeof src === 'string') {
            /* Рядок вставляється як розмітка лише за явного type: 'html' - так
               само робив fancyBox, і так з чужого src не з'явиться нової дірки. */
            body.innerHTML = src;
        } else {
            el.remove();
            return null;
        }

        var dialog = dialogFor(el);
        dialog.on('hide', function () {
            if (inline && anchor) {
                inline.style.display = hidden;
                anchor.parentNode.insertBefore(inline, anchor);
                anchor.remove();
            }
            $.removeData(el, KEY);
            el.remove();
        });
        dialog.show();
        return dialog;
    }

    $.fancybox = {
        open: openShim,
        close: function () {
            $('.' + SHIM).each(function () {
                close(this);
            });
        }
    };

    $.fn.fancybox = function (options) {
        return this.on('click', function (e) {
            e.preventDefault();
            var src = $(this).attr('data-src') || $(this).attr('href');
            openShim($.extend({}, options, {src: src}));
        });
    };

    $(document).on('click', '[data-fancybox]', function (e) {
        e.preventDefault();
        openShim({src: $(this).attr('data-src') || $(this).attr('href')});
    });

    $(document).on('click', '[data-fancybox-close]', function (e) {
        e.preventDefault();
        $.fancybox.close();
    });
})(jQuery, window.A11yDialog);
