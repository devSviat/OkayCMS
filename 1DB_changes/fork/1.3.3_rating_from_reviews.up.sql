-- Рейтинг товару й поста — похідне від схвалених відгуків
-- (Okay\Helpers\RatingHelper::recalculateFromReviews).
--
-- Стокові дані несуть оцінки анонімного голосування, якого у форку немає:
-- сторінка віддає aggregateRating з reviewCount у тисячах, не маючи жодного
-- відгуку. Це не косметика — це structured data, за яку пошук знімає сніпет.
--
-- Одноразове приведення даних до моделі. Об'єкт без відгуків із зірками
-- отримує 0: шаблон друкує розмітку лише при rating > 0, тож товар просто
-- лишається без заявленої оцінки. Відповіді (parent_id <> 0) відгуками не є.
--
-- УВАГА: перезаписує наявні rating/votes. Оновлювач знімає дамп БД перед
-- міграціями (files/backups/pre-update-*.sql); при `ok core:migrate` дамп —
-- на совісті деплою.

-- Єдиний демо-відгук стокового дампа лишався без зірок, тож у демо не було
-- жодного прикладу оцінки. Умова звужена до незміненого стокового рядка: на
-- живому магазині коментар з id 1 — чийсь справжній, його чіпати не можна.
-- Дата в умову не годиться: timestamp читається в часовому поясі сесії.
-- Схвалення не чіпаємо: у стоку відгук на модерації, там йому й місце.
UPDATE `__comments`
SET `rating` = 5
WHERE `id` = 1
  AND `rating` IS NULL
  AND `type` = 'product'
  AND `object_id` = 1
  AND `email` = ''
  AND `name` = 'Андрей';

UPDATE `__products` p
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `__comments`
    WHERE type = 'product' AND approved = 1 AND parent_id = 0 AND rating > 0
    GROUP BY object_id
) c ON c.object_id = p.id
SET p.rating = IFNULL(c.r, 0), p.votes = IFNULL(c.v, 0);

UPDATE `__blog` b
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `__comments`
    WHERE type = 'post' AND approved = 1 AND parent_id = 0 AND rating > 0
    GROUP BY object_id
) c ON c.object_id = b.id
SET b.rating = IFNULL(c.r, 0), b.votes = IFNULL(c.v, 0);
