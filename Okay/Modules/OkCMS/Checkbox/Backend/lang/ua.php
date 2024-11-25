<?php

$lang['okcms__checkbox_title'] = 'Checkbox інтеграція';
$lang['okcms__checkbox_settings_saved'] = 'Налаштування збережені';
$lang['okcms__checkbox_login'] = 'Логін';
$lang['okcms__checkbox_password'] = 'Пароль';
$lang['okcms__checkbox_licenseKey'] = 'Ліцензійний ключ каси';
$lang['okcms__checkbox_orderAvailbaleStatus'] = 'Автоматичне формування та відправка чека при зміні статусу';
$lang['okcms__checkbox_noStatus'] = 'Статус не вибраний';
$lang['okcms__checkbox_receiptText'] = 'Текст для чека';
$lang['okcms__checkbox_devMode'] = 'Тестовий режим роботи';
$lang['okcms__checkbox_cashier_per_manager'] = 'Використовувати окрему касу для різних менеджерів';
$lang['okcms__checkbox_createShift'] = 'Відкрити зміну касира';
$lang['okcms__checkbox_closeShift'] = 'Закрити зміну касира';
$lang['okcms__checkbox_openedShift'] = 'Зміна відкрита';
$lang['okcms__checkbox_justCreatedShift'] = 'Зміна створена, але ще не відкрита';

$lang['okcms__left_checkbox'] = 'Checkbox інтеграція';
$lang['okcms__left_checkbox_settings'] = 'Налаштування';
$lang['okcms__left_checkbox_taxes'] = 'Податкові ставки';
$lang['okcms__left_checkbox_receipts'] = 'Звіти внесення/винесення';
$lang['okcms__left_checkbox_shifts'] = 'Звіти про зміни';

$lang['okcms__checkbox_refresh'] = 'Перевірити зміну';
$lang['okcms__checkbox_showReport'] = 'Показати звіт';
$lang['okcms__checkbox_shifts_no'] = 'Немає закритих змін';

$lang['okcms__checkbox_shiftOpenedAt'] = 'Зміна відкрита в';
$lang['okcms__checkbox_shiftClosedAt'] = 'Зміна закрита в';
$lang['okcms__checkbox_shiftStatus'] = 'Статус';

$lang['okcms__checkbox_errors_empty_params'] = "Не заповнені дані каси.<br>Робота з касою неможлива";
    $lang['okcms__checkbox_errors_no_shift'] = "Не створено зміну касира.<br>Робота з касою неможлива";
$lang['okcms__checkbox_errors_find_order'] = "Замовлення не знайдено. Спробуй пізніше";
$lang['okcms__checkbox_errors_find_purchases'] = "Товари в замовлення не знайдено. Спробуй пізніше";
$lang['okcms__checkbox_errors_empty_receipts_isset'] = "Є необроблені чеки. Створити новий чек неможливо";

$lang['okcms__checkbox_order_receipts'] = 'Чеки замовлення';
$lang['okcms__checkbox_order_receipt_create'] = 'Створити чек';
$lang['okcms__checkbox_order_receipt_create_return'] = 'Створити чек повернення';

$lang['okcms__checkbox_order_receipt_date'] = 'Дата формування чека';
$lang['okcms__checkbox_order_receipt_return'] = 'Повернення';
$lang['okcms__checkbox_order_receipt_print'] = 'Друк';

$lang['okcms__checkbox_orders_receipt_pay'] = 'Чек оплати';
$lang['okcms__checkbox_orders_receipt_return'] = 'Чек повернення';

$lang['okcms__checkbox_receipts_service'] = 'Внести/винести кошти';
$lang['okcms__checkbox_receipts_service_value'] = 'Введіть значення внесення/винесення';
$lang['okcms__checkbox_receipts_no'] = 'Немає операцій внесення/винесення';
$lang['okcms__checkbox_receipts_title'] = 'Звіт операцій внесення/винесення коштів';

$lang['okcms__checkbox_receiptServiceCreatedAt'] = 'Чек створений';
$lang['okcms__checkbox_receiptServiceOperation'] = 'Операція';
$lang['okcms__checkbox_receiptServiceSum'] = 'Сума';
$lang['okcms__checkbox_receiptServiceOperationOut'] = 'Винесення';
$lang['okcms__checkbox_receiptServiceOperationIn'] = 'Внесення';

$lang['okcms__left_checkbox_taxes_add'] = 'Додати податкову групу';
$lang['okcms__left_checkbox_taxes_code'] = 'Код';
$lang['okcms__left_checkbox_taxes_delete'] = 'Видалити податкову групу';
$lang['okcms__left_checkbox_taxes_no'] = 'Немає податкових груп';
$lang['okcms__left_checkbox_taxes_added'] = 'Податкова група додана';
$lang['okcms__left_checkbox_taxes_updated'] = 'Податкову групу оновлено';
$lang['okcms__left_checkbox_taxes_code'] = 'Код в Checkbox';

$lang['okcms__left_checkbox_taxes_errors_code'] = 'Порожній код';
$lang['okcms__left_checkbox_taxes_errors_name'] = 'Порожня назва';
$lang['okcms__left_checkbox_taxes_errors_exists'] = 'Податкова група з таким кодом існує';

$lang['okcms__left_checkbox_taxes_product_tooltip'] = 'Податкова група потрібна для роботи з сервісом Checkbox';

$lang['okcms__checkbox_shifts'] = 'Звіт щодо змін касира';

$lang['okcms__checkbox_type_cash'] = 'Готівка';
$lang['okcms__checkbox_type_cashless'] = 'Безготівковий розрахунок';

$lang['okcms__checkbox_cron_check_shifts_1'] = 'Для того, щоб сайт реагував на відкриття/закриття зміни, необхідно в білінг-панелі хостингу налаштувати дну з наступних cron-задач із періодичністю 2-5 хвилин:';
$lang['okcms__checkbox_cron_check_shifts_2'] = 'АБО';
$lang['okcms__checkbox_cron_check_shifts_3'] = "ВАЖЛИВО! Включати потрібно ТІЛЬКИ ОДНУ із зазначених cron-задач. Працюючи 2-х завдань будуть марно використовуватися ресурси сервера, т.к. Завдання ідентичні.
<br>Переважніше виконувати команду \"php\".
<br>На хостингу, можливо, потрібно буде вказувати інший шлях до команд php або wget";

$lang['okcms__checkbox_cron_check_receipts_empty_1'] = 'Для того, щоб чеки реєструвалися як при відкритій так і при закритій зміні і проводилися при відкритті зміни, потрібно налаштувати дну з наступних cron-задач з періодичністю в 2-5 хвилин:';
$lang['okcms__checkbox_cron_check_receipts_empty_2'] = 'АБО';
$lang['okcms__checkbox_cron_check_receipts_empty_3'] = "ВАЖЛИВО! Включати потрібно ТІЛЬКИ ОДНУ із зазначених cron-задач. Працюючи 2-х завдань будуть марно використовуватися ресурси сервера, т.к. Завдання ідентичні.
<br>Переважніше виконувати команду \"php\".
<br>На хостингу, можливо, потрібно буде вказувати інший шлях до команд php або wget";

$lang['okcms__checkbox_message_howSend'] = 'Надіслати чек по';
$lang['okcms__checkbox_message_notSend'] = 'Не відправляти';
$lang['okcms__checkbox_message_email'] = 'По Email';
$lang['okcms__checkbox_message_sms'] = 'За SMS';
$lang['okcms__checkbox_message_email_sms'] = 'По Email та SMS';
$lang['okcms__checkbox_message_sms_if_not_email'] = 'Email. SMS, якщо не вказано';
$lang['okcms__checkbox_message_email_if_not_sms'] = 'SMS. Email, якщо не вказано телефон';

$lang['okcms__checkbox_order_sended_date'] = 'Повідомлення відправлено';

