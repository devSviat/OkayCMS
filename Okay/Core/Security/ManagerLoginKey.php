<?php

namespace Okay\Core\Security;

use Okay\Core\QueryFactory;

/**
 * Ключ облікового запису для ліміту спроб входу: два логіни мають однаковий
 * ключ тоді й лише тоді, коли база вважає їх тим самим логіном.
 *
 * Тому ключ рахує сама база, вагою в колації стовпця `__managers.login`
 * (ManagerLoginKeyDatabaseTest звіряє це з ManagersEntity::get() на живій базі).
 * Будь-яка нормалізація в PHP розходиться з колацією: `utf8mb4_unicode_ci`
 * не розрізняє діакритики, а пробіл на початку розрізняє. Розбіжність працює
 * як оракул: вигаданий логін вичерпував інший лічильник, ніж справжній, і
 * шоста спроба видавала, чи існує логін.
 */
class ManagerLoginKey
{
    /** @var array{0: string, 1: string, 2: bool}|null кодування, колація, PAD SPACE */
    private ?array $collation = null;

    public function __construct(private QueryFactory $queryFactory)
    {
    }

    public function of(string $login): string
    {
        [$charset, $collation, $padSpace] = $this->collation();

        $rows = $this->queryFactory->newSqlQuery()
            ->setStatement(
                'SELECT HEX(WEIGHT_STRING(CONVERT(:login USING ' . $charset . ') COLLATE ' . $collation . ')) AS w,'
                . ' HEX(WEIGHT_STRING(CONVERT(\' \' USING ' . $charset . ') COLLATE ' . $collation . ')) AS sp'
            )
            ->bindValue('login', $login)
            ->results();
        $row = is_array($rows) ? reset($rows) : null;
        $weight = is_object($row) ? (string)($row->w ?? '') : '';
        $space = is_object($row) ? (string)($row->sp ?? '') : '';

        if ($padSpace) {
            $weight = self::trimTrailingSpaceWeight($weight, $space);
        }

        if ($weight === '') {
            return 'fallback:' . mb_strtolower(trim($login));
        }

        return 'w:' . $weight;
    }

    /**
     * PAD SPACE: база не розрізняє в кінці нічого, що важить як пробіл, — і
     * U+0020, і NBSP, U+2000–U+200A, U+3000 тощо, а WEIGHT_STRING їх рахує.
     * Тому з кінця ваги знімаємо одиниці ваги пробілу, а не символи пробілу:
     * RTRIM знімав лише U+0020, і кожна кількість NBSP у кінці давала новий
     * лічильник для того самого облікового запису.
     */
    public static function trimTrailingSpaceWeight(string $weight, string $space): string
    {
        $unit = strlen($space);
        if ($unit === 0) {
            return $weight;
        }

        while ($weight !== '' && strlen($weight) % $unit === 0 && str_ends_with($weight, $space)) {
            $weight = substr($weight, 0, -$unit);
        }

        return $weight;
    }

    /** @return array{0: string, 1: string, 2: bool} */
    private function collation(): array
    {
        if ($this->collation !== null) {
            return $this->collation;
        }

        $name = $this->queryFactory->newSqlQuery()
            ->setStatement("SHOW FULL COLUMNS FROM __managers WHERE Field = 'login'")
            ->result('Collation');

        // Ім'я йде в текст запиту, тож приймаємо лише ім'я колації.
        if (!is_string($name) || !preg_match('/^([a-z0-9]+)_[a-z0-9_]+$/', $name, $matches)) {
            $name = 'utf8mb4_unicode_ci';
            $matches = [1 => 'utf8mb4'];
        }

        // NO PAD колації (MariaDB `_nopad_`, MySQL 8 `_0900_`) пробіли в кінці розрізняють.
        $padSpace = !str_contains($name, '_nopad_') && !str_contains($name, '_0900_');

        return $this->collation = [$matches[1], $name, $padSpace];
    }
}
