<?php

$lang['left_core_updater_title'] = 'Оновлення ядра';
$lang['core_updater_meta_title'] = 'Оновлення ядра';

$lang['core_updater_installed_label'] = 'Встановлена версія';
$lang['core_updater_available_label'] = 'Доступна версія';
$lang['core_updater_based_on_label'] = 'На основі';
$lang['core_updater_published_label'] = 'Опубліковано';
$lang['core_updater_check_now'] = 'Перевірити зараз';
$lang['core_updater_update_btn'] = 'Оновити';
$lang['core_updater_view_changes'] = 'Переглянути зміни';
$lang['core_updater_confirm_title'] = 'Підтвердження оновлення';
$lang['core_updater_confirm_text'] = 'Оновлення ядра змінить файли сайту, тимчасово увімкне технічні роботи і автоматично створить резервну копію перед стартом. Продовжити?';
$lang['core_updater_confirm_yes'] = 'Так, оновити';
$lang['core_updater_confirm_no'] = 'Скасувати';

$lang['core_updater_step_download'] = 'Завантаження пакета оновлення';
$lang['core_updater_step_verify'] = 'Перевірка цілісності пакета';
$lang['core_updater_step_preflight'] = 'Перевірка перед оновленням';
$lang['core_updater_step_backup'] = 'Створення резервної копії';
$lang['core_updater_step_maintenance_on'] = 'Увімкнення технічних робіт';
$lang['core_updater_step_apply_files'] = 'Застосування файлів';
$lang['core_updater_step_migrations'] = 'Виконання міграцій бази даних';
$lang['core_updater_step_cache_clear'] = 'Очищення кешу';
$lang['core_updater_step_health_check'] = 'Перевірка працездатності сайту';
$lang['core_updater_step_finalize'] = 'Завершення оновлення';
$lang['core_updater_step_done'] = 'Оновлення завершено';
$lang['core_updater_step_failed'] = 'Оновлення не вдалося';
$lang['core_updater_step_rolled_back'] = 'Виконано відкат до попередньої версії';

$lang['core_updater_manual_intervention_text'] = 'Потрібне ручне втручання: перевірте стан сайту та журнал оновлення.';
$lang['core_updater_backup_paths_label'] = 'Шляхи до резервних копій';
$lang['core_updater_migrations_not_rolled_back'] = 'Міграції бази даних автоматично не відкочуються — перевірте застосовані міграції вручну.';
$lang['core_updater_no_migrations_applied_text'] = 'У цьому прогоні жодна міграція бази даних не була застосована.';
$lang['core_updater_stale_run_text'] = 'Попередній прогін оновлення не завершився і вважається обірваним.';
$lang['core_updater_stale_run_maintenance_warning'] = 'Оновлення перервано. Якщо вітрина закрита технічними роботами, вона лишиться закритою до продовження або ручного зняття config/.maintenance (див. docs/updates.md).';
$lang['core_updater_previous_run_done_text'] = 'Попереднє оновлення успішно завершено. Доступна нова версія.';
$lang['core_updater_poll_lost_text'] = 'Звʼязок із сервером втрачено або сесія завершилась — оновіть сторінку, оновлення при цьому продовжується.';
$lang['core_updater_last_check_label'] = 'Остання перевірка';
$lang['core_updater_check_failed_label'] = 'Помилка перевірки';

$lang['core_updater_up_to_date_text'] = 'У вас встановлена остання версія ядра.';
$lang['core_updater_no_data_text'] = 'Оновлення ядра ще не перевірялося.';
$lang['core_updater_whats_new_label'] = 'Що нового';
$lang['core_updater_requires_migrations_badge'] = 'Потребує міграцій бази даних';
$lang['core_updater_resume_btn'] = 'Продовжити оновлення';
$lang['core_updater_retry_btn'] = 'Спробувати ще раз';
$lang['core_updater_docs_link_label'] = 'Інструкція з відновлення';
$lang['core_updater_rolled_back_migrations_label'] = 'Застосовані міграції, які НЕ відкочено автоматично';
$lang['core_updater_maintenance_disabled_text'] = 'Технічні роботи вимкнено після невдалого оновлення.';
$lang['core_updater_ajax_error'] = 'Помилка запиту. Спробуйте ще раз.';
$lang['core_updater_csrf_error'] = 'Сесія застаріла. Оновіть сторінку і спробуйте знову.';
$lang['core_updater_cannot_start'] = 'Оновлення зараз недоступне.';
$lang['core_updater_start_failed'] = 'Не вдалося запустити оновлення.';
