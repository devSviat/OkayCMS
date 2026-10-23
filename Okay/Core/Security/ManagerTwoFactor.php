<?php

namespace Okay\Core\Security;

use Okay\Core\EntityFactory;
use Okay\Entities\ManagerRecoveryCodesEntity;
use Okay\Entities\ManagerTotpEntity;
use Psr\Log\LoggerInterface;

/**
 * Друга сходинка входу менеджера: TOTP і коди відновлення.
 *
 * Секрет у базі — шифротекст XChaCha20-Poly1305 ключем TotpKey, з id
 * менеджера як додатковими даними: шифротекст одного менеджера не
 * розшифрується як чужий. Коди відновлення від ключа не залежать і працюють,
 * навіть коли ключ загублено.
 *
 * Стан у сесії: totp_setup — секрет, який ще не підтвердили кодом (у базу
 * він потрапляє лише після підтвердження); admin_pending — пароль прийнято,
 * чекаємо код. Жоден із них не дає доступу: його дає лише
 * ManagerSessions::signIn().
 */
class ManagerTwoFactor
{
    public const RECOVERY_CODES = 10;
    /** 16 символів Base32 — 80 біт. */
    public const RECOVERY_CODE_LENGTH = 16;
    public const SETUP_TTL_SECONDS = 600;
    public const CHALLENGE_TTL_SECONDS = 300;
    /** Невдалих кодів на одного менеджера за вікно, після чого треба знову вводити пароль. */
    public const CODE_ATTEMPTS = 5;
    public const CODE_WINDOW_SECONDS = 900;
    /**
     * Кожне вичерпане вікно подвоює наступне, до доби: з паролем у руках
     * перебір інакше мав би 480 спроб на добу без кінця.
     */
    public const CODE_WINDOW_MAX_SECONDS = 86400;
    /** Скільки разів перечитати лічильник, якщо паралельна спроба змінила його першою. */
    private const SWAP_RETRIES = 3;

    public const OK = 'ok';
    public const OK_RECOVERY = 'ok_recovery';
    public const WRONG = 'wrong';
    public const LIMIT = 'limit';
    public const KEY_UNAVAILABLE = 'key_unavailable';

    private const SETUP_KEY = 'totp_setup';
    private const CHALLENGE_KEY = 'admin_pending';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private EntityFactory $entityFactory,
        private TotpKey $totpKey,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function isEnabled(int $managerId): bool
    {
        return $this->row($managerId) !== null;
    }

    public function enabledAt(int $managerId): ?string
    {
        $row = $this->row($managerId);

        return $row !== null ? (string)$row->enabled_at : null;
    }

    public function keyStatus(): string
    {
        return $this->totpKey->status();
    }

    public function remainingRecoveryCodes(int $managerId): int
    {
        return (int)$this->codes()->count(['manager_id' => $managerId, 'unused' => true]);
    }

    /**
     * Новий секрет для підключення застосунку. У базу не пишеться, лише в
     * сесію: незавершене підключення не вмикає 2FA і нічого не ламає.
     *
     * @return array{secret: string, uri: string}|null null — ключа немає
     */
    public function startSetup(int $managerId, string $issuer, string $account): ?array
    {
        if ($this->totpKey->key() === null) {
            return null;
        }

        $secret = Totp::newSecret();
        $_SESSION[self::SETUP_KEY] = [
            'manager_id' => $managerId,
            'secret'     => base64_encode($secret),
            'at'         => time(),
        ];

        return ['secret' => Totp::base32Encode($secret), 'uri' => Totp::provisioningUri($secret, $issuer, $account)];
    }

    /** @return array{secret: string, uri: string}|null підключення, що чекає на код */
    public function pendingSetup(int $managerId, string $issuer, string $account): ?array
    {
        $secret = $this->setupSecret($managerId);

        return $secret === null ? null : ['secret' => Totp::base32Encode($secret), 'uri' => Totp::provisioningUri($secret, $issuer, $account)];
    }

    public function cancelSetup(): void
    {
        unset($_SESSION[self::SETUP_KEY]);
    }

    /**
     * Перший код із застосунку вмикає 2FA: секрет шифрується й пишеться в
     * базу, видаються коди відновлення.
     *
     * @return string[]|null коди відновлення (показати один раз) або null — код хибний
     */
    public function confirmSetup(int $managerId, string $code, ?int $now = null): ?array
    {
        $secret = $this->setupSecret($managerId);
        $key = $this->totpKey->key();
        if ($secret === null || $key === null) {
            return null;
        }

        $step = Totp::matchingStep($secret, self::normalizeTotp($code), $now ?? time());
        if ($step === null) {
            return null;
        }

        $totp = $this->totp();
        $totp->deleteOf($managerId);
        $id = $totp->add([
            'manager_id' => $managerId,
            'secret'     => self::encrypt($secret, $managerId, $key),
            'last_step'  => $step,
        ]);
        if (!$id) {
            $this->logger?->error('ManagerTwoFactor: не вдалося записати секрет менеджера ' . $managerId);

            return null;
        }

        $this->cancelSetup();

        return $this->regenerateRecoveryCodes($managerId);
    }

    /**
     * Код другої сходинки: шість цифр TOTP або код відновлення. Обидва
     * одноразові: крок TOTP і код відновлення гасяться одним UPDATE.
     *
     * @return string self::OK | OK_RECOVERY | WRONG | LIMIT | KEY_UNAVAILABLE
     */
    public function verify(int $managerId, mixed $input, ?int $now = null): string
    {
        $input = is_string($input) ? $input : '';
        $now ??= time();

        // Без рядка 2FA вимкнено, а з ним зникли й коди відновлення.
        $row = $this->row($managerId);
        if ($row === null) {
            return self::WRONG;
        }
        if (!$this->claimAttempt($row, $now)) {
            return self::LIMIT;
        }

        $recovery = self::normalizeRecovery($input);
        if ($recovery !== null) {
            if ($this->codes()->consume($managerId, hash('sha256', $recovery))) {
                $this->resetAttempts($managerId);

                return self::OK_RECOVERY;
            }

            return self::WRONG;
        }

        $key = $this->totpKey->key();
        $secret = $key !== null ? self::decrypt((string)$row->secret, $managerId, $key) : null;
        if ($secret === null) {
            $this->logger?->error('ManagerTwoFactor: секрет менеджера ' . $managerId . ' не розшифровується (ключ відсутній або інший)');

            return self::KEY_UNAVAILABLE;
        }

        $step = Totp::matchingStep($secret, self::normalizeTotp($input), $now);
        if ($step === null || !$this->totp()->acceptStep($managerId, $step)) {
            return self::WRONG;
        }

        $this->resetAttempts($managerId);

        return self::OK;
    }

    /**
     * Наступний стан лічильника [failed_count, failed_since, lockouts] або
     * null — ліміт вичерпано. Годинник, що пішов назад, лишає вікно відкритим.
     *
     * @param array{0: int, 1: int, 2: int} $state
     * @return array{0: int, 1: int, 2: int}|null
     */
    public static function nextAttempts(array $state, int $now): ?array
    {
        [$count, $since, $lockouts] = $state;
        if ($now - $since < self::windowSeconds($lockouts)) {
            return $count >= self::CODE_ATTEMPTS ? null : [$count + 1, $since, $lockouts];
        }

        return [1, $now, $count >= self::CODE_ATTEMPTS ? $lockouts + 1 : $lockouts];
    }

    public static function windowSeconds(int $lockouts): int
    {
        $doublings = max(0, min($lockouts, 10));

        return min(self::CODE_WINDOW_SECONDS * (2 ** $doublings), self::CODE_WINDOW_MAX_SECONDS);
    }

    /** @return string[] нові коди; старі перестають діяти */
    public function regenerateRecoveryCodes(int $managerId): array
    {
        $codes = $this->codes();
        $codes->deleteOf($managerId);

        // Повтор лише на випадок збігу UNIQUE(code_hash); стабільна помилка
        // бази не має крутити цикл до max_execution_time.
        $plain = [];
        for ($try = 0; $try < self::RECOVERY_CODES * 2 && count($plain) < self::RECOVERY_CODES; $try++) {
            $code = self::newRecoveryCode();
            if ($codes->add(['manager_id' => $managerId, 'code_hash' => hash('sha256', $code)])) {
                $plain[] = implode('-', str_split($code, 4));
            }
        }
        if (count($plain) < self::RECOVERY_CODES) {
            $this->logger?->error('ManagerTwoFactor: не вдалося записати коди відновлення менеджера ' . $managerId);
        }

        return $plain;
    }

    public function disable(int $managerId): void
    {
        $this->totp()->deleteOf($managerId);
        $this->codes()->deleteOf($managerId);
    }

    /**
     * Пароль прийнято, чекаємо код. Ідентифікатор сесії міняється: зміна
     * рівня привілеїв, як і повний вхід.
     */
    public function startChallenge(object $manager, string $redirect): void
    {
        unset($_SESSION['admin']);
        SessionNames::regenerate();
        $_SESSION[self::CHALLENGE_KEY] = [
            'manager_id' => (int)$manager->id,
            'login'      => (string)$manager->login,
            'at'         => time(),
            'redirect'   => $redirect,
        ];
    }

    /** @return array{manager_id: int, login: string, at: int, redirect: string}|null */
    public function challenge(): ?array
    {
        $pending = $_SESSION[self::CHALLENGE_KEY] ?? null;
        if (!is_array($pending) || !is_int($pending['at'] ?? null) || !is_int($pending['manager_id'] ?? null)) {
            return null;
        }
        if (time() - $pending['at'] >= self::CHALLENGE_TTL_SECONDS || $pending['at'] > time()) {
            $this->clearChallenge();

            return null;
        }

        return $pending;
    }

    public function clearChallenge(): void
    {
        unset($_SESSION[self::CHALLENGE_KEY]);
    }

    /** Шість цифр; пробіли, які лишає копіювання з застосунку, прибираються. */
    private static function normalizeTotp(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }

    /** Код відновлення без дефісів і пробілів, великими літерами, або null. */
    private static function normalizeRecovery(string $input): ?string
    {
        $normalized = strtoupper((string)preg_replace('/[\s-]+/', '', $input));
        if (strlen($normalized) !== self::RECOVERY_CODE_LENGTH || strspn($normalized, self::RECOVERY_ALPHABET) !== self::RECOVERY_CODE_LENGTH) {
            return null;
        }

        return $normalized;
    }

    /** Без 0/O й 1/I: код переписують руками з паперу. 32 символи — 5 біт на символ. */
    private static function newRecoveryCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::RECOVERY_CODE_LENGTH; $i++) {
            $code .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return $code;
    }

    private static function encrypt(string $secret, int $managerId, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret, self::associatedData($managerId), $nonce, $key);
    }

    private static function decrypt(string $blob, int $managerId, string $key): ?string
    {
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($blob) <= $nonceLength) {
            return null;
        }

        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($blob, $nonceLength),
            self::associatedData($managerId),
            substr($blob, 0, $nonceLength),
            $key
        );

        return is_string($plain) ? $plain : null;
    }

    private static function associatedData(int $managerId): string
    {
        return 'okay-manager-totp:' . $managerId;
    }

    private function setupSecret(int $managerId): ?string
    {
        $setup = $_SESSION[self::SETUP_KEY] ?? null;
        if (!is_array($setup) || ($setup['manager_id'] ?? null) !== $managerId || !is_int($setup['at'] ?? null)
            || time() - $setup['at'] >= self::SETUP_TTL_SECONDS) {
            return null;
        }

        $secret = base64_decode((string)($setup['secret'] ?? ''), true);

        return is_string($secret) && strlen($secret) === Totp::SECRET_BYTES ? $secret : null;
    }

    /** Займає спробу в лічильнику бази; будь-яка невдача запису — «ліміт». */
    private function claimAttempt(object $row, int $now): bool
    {
        $managerId = (int)$row->manager_id;
        for ($try = 0; $try < self::SWAP_RETRIES && $row !== null; $try++) {
            $state = [(int)$row->failed_count, (int)$row->failed_since, (int)$row->lockouts];
            $next = self::nextAttempts($state, $now);
            if ($next === null) {
                // Код понад ліміт можна надіслати лише з паролем: власник
                // бачить у лозі, що пароль відомий комусь іще.
                $this->logger?->warning('ManagerTwoFactor: код менеджера ' . $managerId . ' понад ліміт, вікно ' . self::windowSeconds($state[2]) . ' с');

                return false;
            }
            if ($this->totp()->swapAttempts($managerId, $state, $next)) {
                return true;
            }
            $row = $this->row($managerId);
        }

        return false;
    }

    private function resetAttempts(int $managerId): void
    {
        $row = $this->row($managerId);
        if ($row !== null) {
            $this->totp()->swapAttempts($managerId, [(int)$row->failed_count, (int)$row->failed_since, (int)$row->lockouts], [0, 0, 0]);
        }
    }

    private function row(int $managerId): ?object
    {
        if ($managerId <= 0) {
            return null;
        }
        $row = $this->totp()->findOne(['manager_id' => $managerId]);

        return is_object($row) && (int)$row->manager_id === $managerId ? $row : null;
    }

    private function totp(): ManagerTotpEntity
    {
        return $this->entityFactory->get(ManagerTotpEntity::class);
    }

    private function codes(): ManagerRecoveryCodesEntity
    {
        return $this->entityFactory->get(ManagerRecoveryCodesEntity::class);
    }
}
