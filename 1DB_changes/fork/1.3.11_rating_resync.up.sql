-- Повторний перерахунок рейтингу з відгуків (як 1.3.3_rating_from_reviews).
--
-- До 1.3.10 перемикач «схвалено» у списку відгуків адмінки не перераховував
-- бал: відгук, схвалений ним, лишав товар з rating = 0 і без aggregateRating.
-- Код виправлено, а дані, зіпсовані до того, виправляє ця міграція.
--
-- Перераховується все: результат той самий, що дав би
-- RatingHelper::recalculateFromReviews для кожного обʼєкта, тож для
-- узгоджених рядків це no-op. Шкала 1..5 — RatingHelper::MIN_RATING/MAX_RATING.

UPDATE `__products` p
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `__comments`
    WHERE type = 'product' AND approved = 1 AND parent_id = 0 AND rating BETWEEN 1 AND 5
    GROUP BY object_id
) c ON c.object_id = p.id
SET p.rating = IFNULL(c.r, 0), p.votes = IFNULL(c.v, 0);

UPDATE `__blog` b
LEFT JOIN (
    SELECT object_id, AVG(rating) AS r, COUNT(*) AS v
    FROM `__comments`
    WHERE type = 'post' AND approved = 1 AND parent_id = 0 AND rating BETWEEN 1 AND 5
    GROUP BY object_id
) c ON c.object_id = b.id
SET b.rating = IFNULL(c.r, 0), b.votes = IFNULL(c.v, 0);
