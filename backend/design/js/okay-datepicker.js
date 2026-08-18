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
            firstDay: 0
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
     */
    function viewDate(value, format) {
        var parts = String(value).trim().match(/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/);
        var day = format.indexOf('dd');
        var month = format.indexOf('MM');
        var year = format.indexOf('yyyy');

        // Розбираємо лише формати з роком у кінці — інших в адмінці немає. На
        // чужому форматі краще не відкрити потрібний місяць, ніж відкрити чужий.
        if (!parts || day < 0 || month < 0 || year < Math.max(day, month)) {
            return null;
        }

        var date = new Date(+parts[3], (day < month ? +parts[2] : +parts[1]) - 1, day < month ? +parts[1] : +parts[2]);
        return isNaN(date.getTime()) ? null : date;
    }

    function resolve(target) {
        if (typeof target === 'string') {
            return document.querySelectorAll(target);
        }
        // Навколо самий jQuery, тож об'єкт jQuery сюди приїде рано чи пізно.
        return target && target.jquery ? target.get() : [target];
    }

    /**
     * @param {string|Element|jQuery} target Селектор, елемент або набір jQuery.
     * @param {Object} [options] Опції Air Datepicker поверх наших типових.
     * @returns {Array} Створені екземпляри.
     */
    function okayDatepicker(target, options) {
        var locale = locales[okayDatepicker.lang] || locales.en;
        var nodes = resolve(target);

        return Array.prototype.map.call(nodes, function (node) {
            var settings = Object.assign({locale: locale, autoClose: true}, options || {});
            var start = viewDate(node.value, settings.dateFormat || locale.dateFormat);
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
