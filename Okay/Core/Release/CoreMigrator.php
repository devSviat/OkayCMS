<?php

namespace Okay\Core\Release;

class CoreMigrator
{
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
        $statements = [];
        $current = '';

        foreach (file($path) as $line) {
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
