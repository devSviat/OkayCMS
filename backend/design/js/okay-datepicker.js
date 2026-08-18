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
        var parts = String(value).trim().match(/(\d{1,4})[.\/-](\d{1,2})[.\/-](\d{2,4})/);
        if (!parts) {
            return null;
        }
        var dayFirst = format.indexOf('dd') < format.indexOf('MM');
        var date = new Date(+parts[3], (dayFirst ? +parts[2] : +parts[1]) - 1, dayFirst ? +parts[1] : +parts[2]);
        return isNaN(date.getTime()) ? null : date;
    }

    /**
     * @param {string|Element} target Селектор або сам елемент.
     * @param {Object} [options] Опції Air Datepicker поверх наших типових.
     * @returns {Array} Створені екземпляри.
     */
    function okayDatepicker(target, options) {
        var locale = locales[okayDatepicker.lang] || locales.en;
        var nodes = typeof target === 'string' ? document.querySelectorAll(target) : [target];

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
