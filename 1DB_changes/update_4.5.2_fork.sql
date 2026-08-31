-- Схемні зміни форку поверх стокового 4.5.2.
--
-- Ці ж запити лежать окремими міграціями в 1DB_changes/fork/ і на живій
-- інсталяції виконуються самі: оновлювачем з адмінки або `php ok core:migrate`
-- на деплої. Цей файл — для ручного апгрейду зі стоку, коли ані того, ані
-- іншого не було. Виконувати можна повторно: обидва запити ідемпотентні.
--
-- Префікс таблиць тут проставлений (`ok_`); у міграціях на його місці маркер
-- `__`, який підставляється під час застосування.

-- 1.3.2 — оцінка в зірках усередині відгуку.
-- Без колонки CommentsEntity оголошує поле, якого немає в таблиці: вибірка
-- коментарів падає, помилку SQL ковтає обробник, і адмінка відгуків мовчки
-- показує порожній список.
ALTER TABLE `ok_comments` ADD COLUMN IF NOT EXISTS `rating` TINYINT NULL DEFAULT NULL AFTER `user_id`;

-- 1.3.3 — зірки демо-відгуку зі стокового дампа. Умова звужена до
-- незміненого стокового рядка: на живому магазині коментар з id 1 — чийсь
-- справжній.
UPDATE `ok_comments`
SET `rating` = 5
WHERE `id` = 1
  AND `rating` IS NULL
  AND `type` = 'product'
  AND `object_id` = 1
  AND `email` = ''
  AND `name` = 'Андрей';

-- 1.3.3 — рейтинг як похідне від схвалених відгуків.
-- Стокові дані несуть оцінки анонімного голосування, якого у форку немає:
-- сторінка віддає aggregateRating з reviewCount у тисячах, не маючи жодного
-- відгуку. Об'єкт без відгуків із зірками отримує 0 — шаблон друкує розмітку
-- лише при rating > 0.
UPDATE `ok_products` p
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `ok_comments`
    WHERE type = 'product' AND approved = 1 AND parent_id = 0 AND rating > 0
    GROUP BY object_id
) c ON c.object_id = p.id
SET p.rating = IFNULL(c.r, 0), p.votes = IFNULL(c.v, 0);

UPDATE `ok_blog` b
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `ok_comments`
    WHERE type = 'post' AND approved = 1 AND parent_id = 0 AND rating > 0
    GROUP BY object_id
) c ON c.object_id = b.id
SET b.rating = IFNULL(c.r, 0), b.votes = IFNULL(c.v, 0);
