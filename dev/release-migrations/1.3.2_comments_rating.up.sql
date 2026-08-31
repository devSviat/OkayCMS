-- Оцінка в зірках усередині відгуку.
--
-- Без колонки CommentsEntity оголошує поле, якого немає в таблиці: будь-яка
-- вибірка коментарів падає, помилку SQL ковтає обробник, і адмінка відгуків
-- просто показує порожній список без жодного натяку на причину.
--
-- IF NOT EXISTS, бо інсталяції, які виконали 1DB_changes/update_4.5.2_fork.sql
-- вручну, колонку вже мають, а трекер про це не знає (синтаксис MariaDB).

ALTER TABLE `__comments` ADD COLUMN IF NOT EXISTS `rating` TINYINT NULL DEFAULT NULL AFTER `user_id`;
