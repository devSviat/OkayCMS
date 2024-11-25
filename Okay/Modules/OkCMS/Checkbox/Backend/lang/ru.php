<?php

$lang['okcms__checkbox_title'] = 'Checkbox интеграция';
$lang['okcms__checkbox_settings_saved'] = 'Настройки сохранены';
$lang['okcms__checkbox_login'] = 'Логин';
$lang['okcms__checkbox_password'] = 'Пароль';
$lang['okcms__checkbox_licenseKey'] = 'Лицензионный ключ кассы';
$lang['okcms__checkbox_orderAvailbaleStatus'] = 'Автоматическое формирование и отправка чека при смене статуса';
$lang['okcms__checkbox_noStatus'] = 'Статус не выбран';
$lang['okcms__checkbox_receiptText'] = 'Текст для чека';
$lang['okcms__checkbox_devMode'] = 'Тестовый режим работы';
$lang['okcms__checkbox_cashier_per_manager'] = 'Использовать отдельную кассу для разных менеджеров';
$lang['okcms__checkbox_createShift'] = 'Открыть смену кассира';
$lang['okcms__checkbox_closeShift'] = 'Закрыть смену кассира';
$lang['okcms__checkbox_openedShift'] = 'Смена открыта';
$lang['okcms__checkbox_justCreatedShift'] = 'Смена создана, но еще не открыта';

$lang['okcms__left_checkbox'] = 'Checkbox интеграция';
$lang['okcms__left_checkbox_settings'] = 'Настройки';
$lang['okcms__left_checkbox_taxes'] = 'Налоговые ставки';
$lang['okcms__left_checkbox_receipts'] = 'Отчеты внесения/вынесения';
$lang['okcms__left_checkbox_shifts'] = 'Отчеты по сменам';

$lang['okcms__checkbox_refresh'] = 'Проверить смену';
$lang['okcms__checkbox_showReport'] = 'Показать отчет';
$lang['okcms__checkbox_shifts_no'] = 'Нет закрытых смен';

$lang['okcms__checkbox_shiftOpenedAt'] = 'Смена открыта в';
$lang['okcms__checkbox_shiftClosedAt'] = 'Смена закрыта в';
$lang['okcms__checkbox_shiftStatus'] = 'Статус';

$lang['okcms__checkbox_errors_empty_params'] = "Не заполнены данные кассы.<br>Работа с кассой невозможна";
$lang['okcms__checkbox_errors_no_shift'] = "Не создана смена кассира.<br>Работа с кассой невозможна";
$lang['okcms__checkbox_errors_find_order'] = "Заказ не найден. Попробуйте позже";
$lang['okcms__checkbox_errors_find_purchases'] = "Товары в заказе не найдены. Попробуйте позже";
$lang['okcms__checkbox_errors_empty_receipts_isset'] = "Есть не обработанные чеки.<br>Создать новый чек невозможно";

$lang['okcms__checkbox_order_receipts'] = 'Чеки заказа';
$lang['okcms__checkbox_order_receipt_create'] = 'Создать чек';
$lang['okcms__checkbox_order_receipt_create_return'] = 'Создать чек возврата';

$lang['okcms__checkbox_order_receipt_date'] = 'Дата формирования чека';
$lang['okcms__checkbox_order_receipt_return'] = 'Возврат';
$lang['okcms__checkbox_order_receipt_print'] = 'Печать';

$lang['okcms__checkbox_orders_receipt_pay'] = 'Чек оплаты';
$lang['okcms__checkbox_orders_receipt_return'] = 'Чек возврата';

$lang['okcms__checkbox_receipts_service'] = 'Внести/вынести средства';
$lang['okcms__checkbox_receipts_service_value'] = 'Введите значение внесения/вынесения';
$lang['okcms__checkbox_receipts_no'] = 'Нет операций внесения/вынесения';
$lang['okcms__checkbox_receipts_title'] = 'Отчет операций внесения/вынесения средств';

$lang['okcms__checkbox_receiptServiceCreatedAt'] = 'Чек создан';
$lang['okcms__checkbox_receiptServiceOperation'] = 'Операция';
$lang['okcms__checkbox_receiptServiceSum'] = 'Сумма';
$lang['okcms__checkbox_receiptServiceOperationOut'] = 'Вынесение';
$lang['okcms__checkbox_receiptServiceOperationIn'] = 'Внесение';

$lang['okcms__left_checkbox_taxes_add'] = 'Добавить налоговую группу';
$lang['okcms__left_checkbox_taxes_code'] = 'Код';
$lang['okcms__left_checkbox_taxes_delete'] = 'Удалить налоговую группу';
$lang['okcms__left_checkbox_taxes_no'] = 'Нет налоговых групп';
$lang['okcms__left_checkbox_taxes_added'] = 'Налоговая группа добавлена';
$lang['okcms__left_checkbox_taxes_updated'] = 'Налоговая группа обновлена';
$lang['okcms__left_checkbox_taxes_code'] = 'Код в Checkbox';

$lang['okcms__left_checkbox_taxes_errors_code'] = 'Пустой код';
$lang['okcms__left_checkbox_taxes_errors_name'] = 'Пустое название';
$lang['okcms__left_checkbox_taxes_errors_exists'] = 'Налоговая группа с таким кодом уже существует';

$lang['okcms__left_checkbox_taxes_product_tooltip'] = 'Налоговая группа необходима для работы с сервисом Checkbox';

$lang['okcms__checkbox_shifts'] = 'Отчет по сменам кассира';

$lang['okcms__checkbox_type_cash'] = 'Наличные';
$lang['okcms__checkbox_type_cashless'] = 'Безналичный расчет';

$lang['okcms__checkbox_cron_check_shifts_1'] = 'Для того, чтоб сайт реагировал на открытие/закрытие смены, необходимо в биллинг-панели хостинга настроить дну из следующих cron-задач с периодичностью в 2-5 минут:';
$lang['okcms__checkbox_cron_check_shifts_2'] = 'ИЛИ';
$lang['okcms__checkbox_cron_check_shifts_3'] = "ВАЖНО!!! Включать нужно ТОЛЬКО ОДНУ из указанных cron-задач. При работе 2-х задач будут бесполезно использоваться ресурсы сервера, т.к. задачи идентичны.
<br>Предпочтительнее выполнять команду \"php\". 
<br>На хостинге, возможно, нужно будет указывать другой путь к командам php или wget
";

$lang['okcms__checkbox_cron_check_receipts_empty_1'] = 'Для того, чтоб чеки регистрировались как при открытой так и при закрытой смене и проводились при открытии смены нужно настроить дну из следующих cron-задач с периодичностью в 2-5 минут:';
$lang['okcms__checkbox_cron_check_receipts_empty_2'] = 'ИЛИ';
$lang['okcms__checkbox_cron_check_receipts_empty_3'] = "ВАЖНО!!! Включать нужно ТОЛЬКО ОДНУ из указанных cron-задач. При работе 2-х задач будут бесполезно использоваться ресурсы сервера, т.к. задачи идентичны.
<br>Предпочтительнее выполнять команду \"php\".
<br>На хостинге, возможно, нужно будет указывать другой путь к командам php или wget
";

$lang['okcms__checkbox_message_howSend'] = 'Отправить чек по';
$lang['okcms__checkbox_message_notSend'] = 'Не отправлять';
$lang['okcms__checkbox_message_email'] = 'По Email';
$lang['okcms__checkbox_message_sms'] = 'По SMS';
$lang['okcms__checkbox_message_email_sms'] = 'По Email и SMS';
$lang['okcms__checkbox_message_sms_if_not_email'] = 'Email. SMS, если не указан Email';
$lang['okcms__checkbox_message_email_if_not_sms'] = 'SMS. Email, если не указан телефон';

$lang['okcms__checkbox_order_sended_date'] = 'Сообщение отправлено';

