-- Реєстр сесій менеджерів адмінки (ManagerSessions).
--
-- Сесії PHP лежать файлами, і перелічити чи відкликати їх неможливо. Тому
-- кожен повний вхід пишеться сюди, а кожен запит адмінки й вітрини з
-- бекендовою сесією звіряється з цим рядком.
--
-- Сам ідентифікатор сесії сюди не пишемо, лише sha256 від нього: дамп бази
-- не має давати готових кук.

CREATE TABLE IF NOT EXISTS `__manager_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `manager_id` int(11) NOT NULL,
    `sid_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
    `ip` varchar(45) NOT NULL DEFAULT '',
    `user_agent` varchar(255) NOT NULL DEFAULT '',
    `mfa` tinyint(1) NOT NULL DEFAULT 0,
    `revoked_at` datetime DEFAULT NULL,
    `revoked_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sid_hash` (`sid_hash`),
    KEY `manager_id` (`manager_id`),
    KEY `last_seen_at` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
