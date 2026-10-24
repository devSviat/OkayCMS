-- Двофакторна автентифікація менеджерів (ManagerTwoFactor).
--
-- Окремі таблиці, а не колонки в __managers: ManagersEntity віддає всі свої
-- поля в шаблони, модулі й розширення, і шифротекст секрету їздив би разом
-- із менеджером туди, де він не потрібен.
--
-- secret — шифротекст XChaCha20-Poly1305 (nonce || ciphertext), ключ лежить
-- поза базою (OKAY_TOTP_KEY). last_step — останній прийнятий 30-секундний
-- крок: код приймається лише для більшого, тож повтор того самого коду не
-- проходить.
--
-- failed_count / failed_since / lockouts — ліміт хибних кодів. У базі, а не у
-- файлах AttemptLimiter: той при недоступному сховищі пропускає спробу, а тут
-- це прибрало б єдиний бар'єр перед перебором шести цифр.

CREATE TABLE IF NOT EXISTS `__manager_totp` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `manager_id` int(11) NOT NULL,
    `secret` varbinary(255) NOT NULL,
    `enabled_at` datetime NOT NULL DEFAULT current_timestamp(),
    `last_step` bigint(20) NOT NULL DEFAULT 0,
    `failed_count` int(11) NOT NULL DEFAULT 0,
    `failed_since` int(11) NOT NULL DEFAULT 0,
    `lockouts` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Коди відновлення: лише sha256 від коду. Код має 80 біт ентропії, тож
-- перебір хешу з дампу безнадійний і повільний хеш не потрібен.
CREATE TABLE IF NOT EXISTS `__manager_recovery_codes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `manager_id` int(11) NOT NULL,
    `code_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `used_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code_hash` (`code_hash`),
    KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
