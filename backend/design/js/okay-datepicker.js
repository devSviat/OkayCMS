/**
 * Air Datepicker під адмінку: локалі мов адмінки і фабрика з нашими типовими
 * налаштуваннями.
 *
 * Локалі зібрані сюди з апстріму, бо той віддає їх у форматі CommonJS, який у
 * браузері не працює. Формати дат — ті самі, що були в jQuery UI: значення полів
 * читає strtotime() на боці PHP, і тиха зміна формату зламала б фільтри.
 *
 * Мову ставить index.tpl: okayDatepicker.lang = '<мова менеджера>'.
 */
(function () {
    'use strict';

    var locales = {
        en: {
            days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            today: 'Today',
            clear: 'Clear',
            dateFormat: 'MM/dd/yyyy',
            timeFormat: 'hh:mm aa',
            // Апстрім дає тут неділю, але адмінка завжди починала тиждень
            // з понеділка — в усіх трьох мовах.
            firstDay: 1
        },
        ru: {
            days: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
            daysShort: ['Вос', 'Пон', 'Вто', 'Сре', 'Чет', 'Пят', 'Суб'],
            daysMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
            months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            monthsShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
            today: 'Сегодня',
            clear: 'Очистить',
            dateFormat: 'dd.MM.yyyy',
            timeFormat: 'HH:mm',
            firstDay: 1
        },
        ua: {
            days: ['Неділя', 'Понеділок', 'Вівторок', 'Середа', 'Четвер', 'П’ятниця', 'Субота'],
            daysShort: ['Нед', 'Пнд', 'Вів', 'Срд', 'Чтв', 'Птн', 'Сбт'],
            daysMin: ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
            months: ['Січень', 'Лютий', 'Березень', 'Квітень', 'Травень', 'Червень', 'Липень', 'Серпень', 'Вересень', 'Жовтень', 'Листопад', 'Грудень'],
            monthsShort: ['Січ', 'Лют', 'Бер', 'Кві', 'Тра', 'Чер', 'Лип', 'Сер', 'Вер', 'Жов', 'Лис', 'Гру'],
            today: 'Сьогодні',
            clear: 'Очистити',
            dateFormat: 'dd.MM.yyyy',
            timeFormat: 'HH:mm',
            firstDay: 1
        }
    };

    /**
     * Дата з поля — щоб календар відкривався на тому місяці, який у полі, як це
     * робив jQuery UI. Саме поле при цьому не переписується: у полі дати статті
     * лежить ще й час, і втрачати його від самого лише відкриття сторінки не можна.
     *
     * Порядок частин визначає роздільник, а не локаль — те саме правило, за яким
     * читає strtotime(): слеш означає американський m/d/Y, крапка й дефіс —
     * європейський d.m.Y. Локалі тут довіряти не можна: поле дати статті малює
     * PHP форматом d.m.Y незалежно від мови адмінки.
     */
    function viewDate(value) {
        var parts = String(value).trim().match(/(\d{1,2})([.\/-])(\d{1,2})\2(\d{4})(?:[ T](\d{1,2}):(\d{2}))?/);
        if (!parts) {
            return null;
        }

        var dayFirst = parts[2] !== '/';
        var date = new Date(
            +parts[4],
            (dayFirst ? +parts[3] : +parts[1]) - 1,
            dayFirst ? +parts[1] : +parts[3],
            +(parts[5] || 0),
            +(parts[6] || 0)
        );
        return isNaN(date.getTime()) ? null : date;
    }

    function resolve(target) {
        if (typeof target === 'string') {
            return document.querySelectorAll(target);
        }
        if (!target) {
            return [];
        }
        // Навколо самий jQuery, тож об'єкт jQuery сюди приїде рано чи пізно.
        return target.jquery ? target.get() : [target];
    }

    /**
     * @param {string|Element|jQuery} target Селектор, елемент або набір jQuery.
     * @param {Object} [options] Опції Air Datepicker поверх наших типових.
     * @returns {Array} Створені екземпляри.
     */
    function okayDatepicker(target, options) {
        // Бібліотеку підключають лише на сторінках із полями дат. Тиха відмова
        // краща за виняток, який забирає з собою решту скрипта сторінки.
        if (typeof AirDatepicker === 'undefined') {
            return [];
        }

        var locale = locales[okayDatepicker.lang] || locales.en;
        var nodes = resolve(target);

        return Array.prototype.map.call(nodes, function (node) {
            // showOtherMonths: дні сусідніх місяців малюються приглушено (1.6:1 на
            // білому) і при цьому клікабельні. jQuery UI їх не показував узагалі.
            // toggleSelected: повторний клік по вибраній даті чистив поле, а
            // jQuery UI так не робив; на даті оновлення статті це тихо стирало
            // значення. showOtherMonths: дні сусідніх місяців малюються приглушено
            // (1.6:1 на білому) і при цьому клікабельні — jQuery UI їх не показував.
            var settings = Object.assign({
                locale: locale,
                autoClose: true,
                showOtherMonths: false,
                toggleSelected: false
            }, options || {});
            var start = viewDate(node.value);
            if (start && !settings.startDate) {
                settings.startDate = start;
            }
            return new AirDatepicker(node, settings);
        });
    }

    okayDatepicker.lang = 'en';
    okayDatepicker.locales = locales;

    window.okayDatepicker = okayDatepicker;
})();
