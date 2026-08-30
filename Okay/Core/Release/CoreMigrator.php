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

    public function ensureTable(): void
    {
        $this->requireDb();

        $table = $this->config->get('db_prefix') . 'core_migrations';

        // Самостворення трекера: мігратор мусить працювати і з CLI посеред
        // оновлення, коли install() модуля ще/вже не викликався.
        $this->pdo->perform(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /** @return list<string> імена застосованих цим викликом міграцій */
    public function apply(string $migrationsDir): array
    {
        $this->requireDb();
        $this->ensureTable();

        /** @var CoreMigrationsEntity $migrationsEntity */
        $migrationsEntity = $this->entityFactory->get(CoreMigrationsEntity::class);

        $appliedNames = $migrationsEntity->cols(['name'])->noLimit()->find();
        $appliedNow = [];

        foreach ($this->pending($migrationsDir, $appliedNames) as $migration) {
            foreach ($this->splitSqlFile($migration['path']) as $statement) {
                try {
                    $this->pdo->perform($statement);
                } catch (\PDOException $e) {
                    // Стоп одразу: продовжувати після невдалого стейтмента -
                    // отримати неконсистентну схему з виглядом успіху.
                    throw new \RuntimeException(
                        "Core-міграція {$migration['name']} впала на стейтменті: "
                        . mb_substr(trim($statement), 0, 200),
                        0,
                        $e
                    );
                }
            }

            $migrationsEntity->add([
                'name' => $migration['name'],
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
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

        usort($pending, fn($a, $b) => strcmp($a['name'], $b['name']));

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
