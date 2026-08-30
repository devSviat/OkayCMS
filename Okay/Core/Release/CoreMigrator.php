<?php

namespace Okay\Core\Release;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Entities\CoreMigrationsEntity;

class CoreMigrator
{
    public function __construct(
        private readonly ?ExtendedPdo $pdo = null,
        private readonly ?EntityFactory $entityFactory = null,
        private readonly ?Config $config = null
    ) {
    }

    private function requireDb(): void
    {
        if ($this->pdo === null || $this->entityFactory === null || $this->config === null) {
            throw new \LogicException('CoreMigrator: apply()/ensureTable() потребують повної конструкції через DI');
        }
    }

    /**
     * Читає `db_prefix` з конфігу. Відсутній/порожній ключ — зламаний
     * конфіг, а не свідомий вибір: цей форк завжди постачає `ok_`, і
     * трекер міграцій із порожнім префіксом розійдеться з
     * CoreMigrationsEntity (яка читає `ok_core_migrations`).
     */
    private function resolveTablePrefix(): string
    {
        $prefix = $this->config->get('db_prefix');
        if (!is_string($prefix) || $prefix === '') {
            throw new \RuntimeException("CoreMigrator: конфіг 'db_prefix' відсутній або порожній");
        }

        return $prefix;
    }

    /**
     * Замінює маркер `__` префіксом таблиць — той самий синтаксис
     * міграцій, що і в решті бази (`__products` тощо). Дзеркалить
     * регекс Database::tablePrefix() (Okay/Core/Database.php), тримати
     * синхронізованим при зміні того методу.
     */
    public function prefixTables(string $sql, string $prefix): string
    {
        return preg_replace('/([^"\'0-9a-z_])__([a-z_]+[^"\'])/i', '$1' . $prefix . '$2', $sql);
    }

    public function ensureTable(): void
    {
        $this->requireDb();

        $prefix = $this->resolveTablePrefix();

        // Самостворення трекера: мігратор мусить працювати і з CLI посеред
        // оновлення, коли install() модуля ще/вже не викликався.
        $this->pdo->perform($this->prefixTables(
            "CREATE TABLE IF NOT EXISTS `__core_migrations` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $prefix
        ));
    }

    /**
     * @return list<string> імена застосованих цим викликом міграцій
     * @throws CoreMigrationException якщо якась міграція впала — несе
     *         список уже застосованих у цьому запуску імен
     */
    public function apply(string $migrationsDir): array
    {
        $this->requireDb();
        $this->ensureTable();

        $prefix = $this->resolveTablePrefix();

        /** @var CoreMigrationsEntity $migrationsEntity */
        $migrationsEntity = $this->entityFactory->get(CoreMigrationsEntity::class);

        $appliedNames = $migrationsEntity->cols(['name'])->noLimit()->find();
        $appliedNow = [];

        foreach ($this->pending($migrationsDir, $appliedNames) as $migration) {
            foreach ($this->splitSqlFile($migration['path']) as $statement) {
                try {
                    $this->pdo->perform($this->prefixTables($statement, $prefix));
                } catch (\PDOException $e) {
                    // Стоп одразу: продовжувати після невдалого стейтмента -
                    // отримати неконсистентну схему з виглядом успіху.
                    throw new CoreMigrationException(
                        "Core-міграція {$migration['name']} впала на стейтменті: "
                        . mb_substr(trim($statement), 0, 200),
                        $appliedNow,
                        $e
                    );
                }
            }

            // Пряме perform() замість Entity::add(): той ковтає \Exception і
            // повертає false, а lastInsertId() на MySQL після невдалого
            // INSERT віддає id попереднього успішного вставлення — перевірка
            // на false тоді мовчки проходить.
            try {
                $this->pdo->perform(
                    $this->prefixTables(
                        'INSERT INTO `__core_migrations` (`name`, `applied_at`) VALUES (:name, :applied_at)',
                        $prefix
                    ),
                    [
                        'name' => $migration['name'],
                        'applied_at' => date('Y-m-d H:i:s'),
                    ]
                );
            } catch (\PDOException $e) {
                throw new CoreMigrationException(
                    "Core-міграція {$migration['name']} виконалась, але запис у трекер не вдався",
                    $appliedNow,
                    $e
                );
            }
            $appliedNow[] = $migration['name'];
        }

        return $appliedNow;
    }

    /** @return list<array{name: string, path: string}> */
    public function pending(string $migrationsDir, array $appliedNames): array
    {
        $pending = [];

        foreach (glob(rtrim($migrationsDir, '/') . '/*.up.sql') ?: [] as $path) {
            $name = basename($path);
            if (!in_array($name, $appliedNames, true)) {
                $pending[] = ['name' => $name, 'path' => $path];
            }
        }

        // strcmp сортує "1.11.0_x" перед "1.2.0_y" (посимвольно '1' < '2');
        // натуральне порівняння читає "11" і "2" як числа.
        usort($pending, fn($a, $b) => strnatcmp($a['name'], $b['name']));

        return $pending;
    }

    /** @return list<string> повні SQL-стейтменти файла, без коментарів */
    public function splitSqlFile(string $path): array
    {
        $lines = file($path);
        if ($lines === false) {
            throw new \RuntimeException("Не вдалось прочитати файл міграції: {$path}");
        }

        $statements = [];
        $current = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '--') || trim($line) === '') {
                continue;
            }

            $current .= $line;
            if (str_ends_with(trim($line), ';')) {
                $statements[] = $current;
                $current = '';
            }
        }

        return $statements;
    }
}
