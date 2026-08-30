-- Тестова міграція: два стейтменти
CREATE TABLE IF NOT EXISTS `test_a` (id INT);
ALTER TABLE `test_a` ADD COLUMN r INT;
