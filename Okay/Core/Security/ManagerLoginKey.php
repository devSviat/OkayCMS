<?php

namespace Okay\Core\Security;

use Okay\Core\QueryFactory;

/**
 * Ключ облікового запису для ліміту спроб входу: два логіни мають однаковий
 * ключ тоді й лише тоді, коли база вважає їх тим самим логіном.
 *
 * Тому ключ рахує сама база, вагою в колації стовпця `__managers.login`.
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

        // PAD SPACE: база не розрізняє пробілів у кінці, а WEIGHT_STRING їх
        // рахує. Пробіли на початку значущі й лишаються.
        $value = $padSpace ? 'RTRIM(CONVERT(:login USING ' . $charset . '))' : 'CONVERT(:login USING ' . $charset . ')';
        $weight = $this->queryFactory->newSqlQuery()
            ->setStatement('SELECT HEX(WEIGHT_STRING(' . $value . ' COLLATE ' . $collation . ')) AS w')
            ->bindValue('login', $login)
            ->result('w');

        if (!is_string($weight) || $weight === '') {
            return 'fallback:' . mb_strtolower(trim($login));
        }

        return 'w:' . $weight;
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
