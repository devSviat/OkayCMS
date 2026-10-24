<?php

namespace Security;

use Aura\Sql\ExtendedPdo;
use Okay\Core\EntityFactory;
use Okay\Core\Security\ManagerLoginKey;
use Okay\Core\ServiceLocator;
use Okay\Entities\ManagersEntity;
use PHPUnit\Framework\TestCase;

/**
 * Головна властивість ліміту облікового запису, на живій базі: два написання
 * мають один ключ тоді й лише тоді, коли ManagersEntity::get() знаходить за
 * ними того самого менеджера. Розбіжність в один бік — обхід ліміту, в інший —
 * оракул існування логіна. Мок бази цього не бачить, тож тест іде проти
 * MariaDB і пропускається, де її немає (CI без бази, запуск з хоста).
 *
 * Менеджери створюються в транзакції й відкочуються.
 */
class ManagerLoginKeyDatabaseTest extends TestCase
{
    private const LOGINS = ['kt', 'kt café', 'r3straße'];

    /** Символи, які порівняння в колації може ігнорувати або зводити. */
    private const VARIANTS = [
        '', ' ', '  ', "\u{00A0}", "\u{2003}", "\u{3000}", "\u{202F}", " \u{200B}", "\u{200B}",
        "\t", "\n", "\u{00AD}", "\u{FEFF}", "\0",
    ];

    public function testKeyMatchesWhatTheDatabaseConsidersOneLogin(): void
    {
        $pdo = $this->pdo();
        /** @var ManagersEntity $managers */
        $managers = ServiceLocator::getInstance()->getService(EntityFactory::class)->get(ManagersEntity::class);
        $key = new ManagerLoginKey(ServiceLocator::getInstance()->getService(\Okay\Core\QueryFactory::class));
        $table = $this->table($pdo);

        $pdo->beginTransaction();
        try {
            foreach (self::LOGINS as $login) {
                $pdo->perform("INSERT INTO {$table} (lang, login, email, password) VALUES ('en', :login, '', 'x')", ['login' => $login]);
            }

            $mismatches = [];
            foreach (self::LOGINS as $login) {
                $realKey = $key->of($login);
                foreach ($this->spellings($login) as $spelling) {
                    $found = $managers->get($spelling);
                    $sameManager = is_object($found) && $found->login === $login;
                    $sameKey = $key->of($spelling) === $realKey;
                    if ($sameManager !== $sameKey) {
                        $mismatches[] = sprintf('%s ← %s: get()=%s, ключ=%s', $login, bin2hex($spelling), $sameManager ? 'той самий' : 'інший', $sameKey ? 'той самий' : 'інший');
                    }
                }
            }
        } finally {
            $pdo->rollBack();
        }

        $this->assertSame([], $mismatches);
    }

    /** @return string[] */
    private function spellings(string $login): array
    {
        $spellings = [mb_strtoupper($login), strtr($login, ['é' => 'e', 'ß' => 'ss', 'a' => 'á'])];
        foreach (self::VARIANTS as $variant) {
            $spellings[] = $login . $variant;
            $spellings[] = $variant . $login;
            $spellings[] = $login . $variant . $variant;
        }

        return $spellings;
    }

    private function pdo(): ExtendedPdo
    {
        try {
            $pdo = ServiceLocator::getInstance()->getService(ExtendedPdo::class);
            $pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            $this->markTestSkipped('немає бази: ' . $e->getMessage());
        }

        return $pdo;
    }

    private function table(ExtendedPdo $pdo): string
    {
        $prefix = (string)ServiceLocator::getInstance()->getService(\Okay\Core\Config::class)->get('db_prefix');

        return preg_match('/^[a-z0-9_]*$/i', $prefix) ? $prefix . 'managers' : $this->fail('підозрілий префікс таблиць');
    }
}
