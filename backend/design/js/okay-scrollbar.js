/**
 * Смуга прокрутки поверх вмісту.
 *
 * Потрібна рівно там, де рідна смуга не годиться: у бічному меню вона забирає
 * ширину контейнера й зсуває пункти вліво. Тут прокрутка лишається рідною -
 * колесо, клавіші, тач працюють самі, - а скрипт лише малює положення й дає
 * тягнути повзунок мишею.
 *
 * Вмикається класом fn_scroll_overlay; батько контейнера має бути позиційованим.
 */
(function () {
    'use strict';

    var MIN_THUMB = 24;

    function attach(box) {
        var host = box.parentNode;
        if (!host) {
            return;
        }

        var track = document.createElement('div');
        track.className = 'okay_scrollbar';
        track.setAttribute('aria-hidden', 'true');
        var thumb = document.createElement('div');
        thumb.className = 'okay_scrollbar__thumb';
        track.appendChild(thumb);
        host.appendChild(track);

        function update() {
            var visible = box.clientHeight;
            var total = box.scrollHeight;
            if (total <= visible + 1) {
                track.classList.remove('is-active');
                return;
            }
            track.classList.add('is-active');

            var trackHeight = track.clientHeight;
            var height = Math.max(MIN_THUMB, Math.round(trackHeight * visible / total));
            var offset = Math.round((trackHeight - height) * box.scrollTop / (total - visible));
            thumb.style.height = height + 'px';
            thumb.style.transform = 'translateY(' + offset + 'px)';
        }

        box.addEventListener('scroll', update, {passive: true});
        window.addEventListener('resize', update);

        /* Висота списку змінюється сама - розкриті підменю, лічильники з ajax. */
        if (window.ResizeObserver) {
            var observer = new ResizeObserver(update);
            observer.observe(box);
            if (box.firstElementChild) {
                observer.observe(box.firstElementChild);
            }
        }

        var startY = 0;
        var startTop = 0;

        function onMove(e) {
            var trackHeight = track.clientHeight;
            var height = thumb.offsetHeight;
            if (trackHeight === height) {
                return;
            }
            var ratio = (box.scrollHeight - box.clientHeight) / (trackHeight - height);
            box.scrollTop = startTop + (e.clientY - startY) * ratio;
        }

        function onUp() {
            track.classList.remove('is-dragging');
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
        }

        thumb.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            startY = e.clientY;
            startTop = box.scrollTop;
            track.classList.add('is-dragging');
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        });

        /* Клік по доріжці - сторінка вгору або вниз, як у рідної смуги. */
        track.addEventListener('pointerdown', function (e) {
            if (e.target === thumb) {
                return;
            }
            var rect = thumb.getBoundingClientRect();
            box.scrollTop += (e.clientY < rect.top ? -1 : 1) * box.clientHeight;
        });

        update();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('.fn_scroll_overlay'), attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
