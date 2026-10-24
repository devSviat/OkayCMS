-- Журнал безпеки менеджерів (ManagerAuthLog): входи, невдалі спроби, 2FA,
-- завершення сесій. Менеджер бачить свої події, власник права
-- manager_sessions — усі. Рядки старші за 90 днів прибирає планувальник.
--
-- manager_id NULL — спроба на логін, якого немає. Сам набраний логін не
-- пишемо: у це поле часто вводять пароль. details — короткий JSON без
-- секретів (спосіб входу, хто й звідки вимкнув 2FA тощо).
--
-- ip_bucket — мережа клієнта так, як її рахують ліміти входу (IPv6 за /64):
-- повтори «ліміту» відсіюються за нею, а не за точною адресою, бо з однієї
-- /64 адрес мільярди. Індекси — під ці перевірки й під загальну стелю подій.

CREATE TABLE IF NOT EXISTS `__manager_auth_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `manager_id` int(11) DEFAULT NULL,
    `actor_id` int(11) DEFAULT NULL,
    `event` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `ip` varchar(45) NOT NULL DEFAULT '',
    `ip_bucket` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `user_agent` varchar(255) NOT NULL DEFAULT '',
    `details` varchar(500) NOT NULL DEFAULT '',
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `manager_created` (`manager_id`, `created_at`),
    KEY `created_at` (`created_at`),
    KEY `event_created` (`event`, `created_at`),
    KEY `event_bucket` (`event`, `ip_bucket`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
