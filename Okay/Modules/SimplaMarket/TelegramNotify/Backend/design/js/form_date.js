$(document).ready(function () {
    let time_params = {
        dayOfWeekStart: 1,
        step: 60,
        format: 'H:i:00',
        datepicker: false,
        lang: 'ru',


    };
    $('#datetimepicker_from').datetimepicker(time_params);
    $('#datetimepicker_to').datetimepicker(time_params);
});