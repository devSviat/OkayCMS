-- Модуль CoreUpdater перейменовано з вендора OkayCMS на Sviat: реєстрацію
-- встановленого модуля треба перевести на новий вендор, інакше після
-- оновлення файлів ядро шукатиме Init у старому каталозі.
UPDATE __modules SET vendor = 'Sviat' WHERE vendor = 'OkayCMS' AND module_name = 'CoreUpdater';
