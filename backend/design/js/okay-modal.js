/**
 * Модалки адмінки: data-toggle="modal" відкриває вікно за data-target,
 * data-dismiss="modal" і клік повз діалог закривають. Показ малює CSS за класом
 * in, фокус, Esc і повернення фокуса тримає a11y-dialog.
 *
 * Тут же $.fancybox - назва лишилась публічною для шаблонів і модулів, а під
 * нею та сама модалка. Підтримані форми: open({src:'#id'}),
 * open({src:'<html>', type:'html'}), $(el).fancybox(), data-fancybox +
 * data-src, data-fancybox-close, close().
 */
(function ($, A11yDialog) {
    'use strict';

    /* До запобіжника: index.tpl пише сюди підпис кнопки закриття, і без цього
       рядка той скрипт упаде разом з рештою сторінки. */
    window.okayModal = window.okayModal || {};

    if (!A11yDialog) {
        return;
    }

    var KEY = 'okayModal';
    var SHIM = 'fn_modal_shim';

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

    /* Підкладка в адмінці схована стилями, тож клік повз діалог ловить сама
       .modal - вона на весь екран. */
    $(document).on('click', '.modal', function (e) {
        if (e.target === this) {
            close(this);
        }
    });

    /* ---- шим fancyBox ------------------------------------------------- */

    /* Inline-вміст на час показу переїжджає в оболонку й повертається на місце
       при закритті - інакше друге відкриття його не знайде. */
    function shell() {
        var el = document.createElement('div');
        el.className = 'modal fade ' + SHIM;
        el.setAttribute('role', 'dialog');
        el.innerHTML = '<div class="modal-dialog"><div class="modal-content">' +
            '<button type="button" class="modal_shim_close" data-dismiss="modal"></button>' +
            '<div class="modal-body"></div></div></div>';
        el.querySelector('.modal_shim_close').setAttribute('aria-label', window.okayModal.closeLabel || 'Close');
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
            /* На сторінці цей блок схований - показує його той, хто відкриває. */
            hidden = inline.style.display;
            inline.style.display = '';
        } else if (opts.type === 'html' && typeof src === 'string') {
            /* Розмітку вставляємо лише за явного type: 'html' - інакше будь-який
               src став би точкою вставки HTML. */
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
