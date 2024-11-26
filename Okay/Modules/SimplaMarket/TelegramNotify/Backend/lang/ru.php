<?php

$lang['notify_telegram_module_title'] = 'Уведомления  в телеграм';
$lang['notify_telegram_instruction_title'] = 'Инструкция';
$lang['notify_telegram_instruction_description'] = 'С помощью данной формы, можно включить оповещение в телеграм о заказе. Чтобы создать бота вам необходимо найти в телеграме канал BotBather и через него создать бота. Затем вы получите токен, который нужно ввести в соответствующее поле. После этого необходимо создать чат и добавить в него своего бота, после чего открыть урл https://api.telegram.org/bot&lt;YouToken&gt;/getUpdates и взять chatId (он будет начинаться с минуса)';
$lang['notify_telegram_instruction_tags'] = 'Также можно вставить специальные теги в сообщение:';
$lang['notify_telegram_instruction_order_id'] = 'Номер заказа';
$lang['notify_telegram_instruction_total_price'] = 'Сумма заказа';
$lang['notify_telegram_instruction_name'] = 'Имя';
$lang['notify_telegram_instruction_phone'] = 'Телефон';
$lang['notify_telegram_check_label'] = 'Включить оповещение о заказе в Telegram';
$lang['notify_telegram_bot_token_label'] = 'Bot token (начинается со слова bot)';
$lang['notify_telegram_chat_id_label'] = 'Chat Id';
$lang['notify_telegram_message_label'] = 'Текст сообщения';
$lang['notify_telegram_button_label'] = 'Сохранить';
$lang['notify_telegram_message_label_comment'] = 'Оставление комментария';
$lang['notify_telegram_instruction_text'] = 'Текст комментария';
$lang['notify_telegram_instruction_target_entity_name'] = 'Название сущности назначения (товара или записи блога)';
$lang['notify_telegram_instruction_target_entity_url'] = 'URL сущности назначения (товара или записи блога)';
$lang['notify_telegram_message_label_callback'] = 'Заявки на скачивание';
$lang['notify_telegram_instruction_page_name']  = 'Название страницы';
$lang['notify_telegram_instruction_page_url']  = 'URL страницы';
$lang['notify_telegram_new_comment'] = 'Уведомление об оставленном комментарии';
$lang['notify_telegram_new_callback'] = 'Уведомление об оставленной обратной связи';
$lang['notify_telegram_new_order_not_processed']  = 'Уведомление о заказе, который требует обработки';
$lang['notify_telegram_order_payment_message'] = 'Уведомление об оплате заказа';
$lang['notify_telegram_not_paid_order_message'] = 'Уведомление о неоплаченном заказе (CRON)';
$lang['notify_telegram_new_service_order'] = 'Уведомление об оформленной заявке';
$lang['notify_telegram_not_processed_service_order'] = 'Уведомление о необработанной заявке';
$lang['notify_telegram_is_registered'] = 'Зарегистрирован ли пользователь';
$lang['notify_telegram_user_group'] = 'Группа пользователя';
$lang['notify_telegram_order_payment_method']  = 'Способ оплаты';
$lang['notify_telegram_order_backend_url']  = 'Ссылка на страницу заказа';
$lang['notify_telegram_new_str'] = 'Перенос на новую строку';
$lang['notify_telegram_comments_backend_url']  = "Страница комментариев в админ панели";
$lang['notify_telegram_cms_download']  = 'Уведомления о скачивании системы';
$lang['notify_telegram_downloads_count']  = 'Количество загрузок от пользователя';
$lang['notify_telegram_service_order_source']  = 'Страница с которой пришла заявка';
$lang['notify_telegram_site_url']  = 'Ссылка на сайт клиента';
$lang['notify_telegram_site_cms']  = 'Текущая система сайта';
$lang['notify_telegram_service_order_description']  = 'Описание задачи';
$lang['notify_telegram_backend_service_page']  = 'Ссылка на страницу заявки';
$lang['notify_telegram_service_order_file']  = 'Файл';
$lang['notify_telegram_zero_price_order'] = 'Уведомление о заказе, которые не  требует обработки';
$lang['notify_telegram_purchases'] = "Выводит список товаров заказа в формате \"Название товара - цена товара\" ";
$lang['notify_telegram_product_price'] = "Цена товара";
$lang['notify_telegram_user_registered_mess'] = 'Уведомление о регистрации пользователя';
$lang['notify_telegram_registered'] = 'Зарегистрирован';
$lang['notify_telegram_not_registered'] = 'Не зарегистрирован';
$lang['notify_telegram_registered_cms'] = 'Зарегистрирован';
$lang['notify_telegram_not_registered_cms'] = 'Не зарегистрирован';
$lang['notify_telegram_user_is_group_member'] = 'состоит в группе :';
$lang['notify_telegram_user_is_not_group_member'] = 'не состоит в группе';
$lang['notify_telegram_form_name'] = 'Оформлена заявка  через форму  ';
$lang['notify_telegram_sent_time_instruction'] = "Модуль  может отправлять уведомления в Телеграм без звука в определенное время суток. Для работы этой функции необходимо включить галочку \"Звуковые уведомления по времени  \" и выбрать временой отрезок  ( \"Начиная с\" - начиная с этого времени уведомления будут присывалться со звуком и  \"До\" - до этого времени уведомления будут со звуом)   в котром вы хотите получать уведомления со звуком.Все уведомления ,котрые будут отправляться вне этого диапазона будут отправлены без звука.    ";
$lang['notify_telegram_notify_from_time'] = 'Начиная с ';
$lang['notify_telegram_notify_to_time'] = 'До';
$lang['notify_telegram_notify_by_time'] = 'Звуковые уведомления по времени';
$lang['notify_telegram_time_alert']   = "Время указанное в поле \"Начиная с \" должно быть меньше за время указанное в поле \"До\"";
$lang['notify_telegram_instruction_description_cron'] = "Для корректной работы Cron при создании задачи необходимо передавать параметр root_url,где будет домен вашего сайта ,например, root_url=https://your-domain.com";
$lang['notify_telegram_callback_message_pageurl'] = " Страница с которой отправили обраную связь";
$lang['notify_telegram_callback_name'] = "Имя, кто отправил обратную связь ";
$lang['notify_telegram_callback_message'] = "Сообщение обратной связи";
