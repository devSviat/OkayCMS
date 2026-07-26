<?php

namespace Security;

use Okay\Modules\OkayCMS\AutoDeploy\Helpers\DeployHelper;
use PHPUnit\Framework\TestCase;

class AutoDeployCommandInjectionTest extends TestCase
{
    /**
     * Значення каналу зберігалось у налаштуваннях без перевірки й звідти
     * потрапляло в командний рядок. Перевіряємо, що після екранування
     * ін'єкція лишається частиною аргументу, а не стає окремою командою.
     *
     * @dataProvider injectionProvider
     */
    public function testInjectionPayloadStaysInsideASingleArgument($branch)
    {
        $command = DeployHelper::buildDeployCommand('/srv/module', '/usr/bin/', $branch);

        // Розбираємо командний рядок так само, як це зробив би шелл.
        $argv = self::shellSplit($command);

        $this->assertNotFalse($argv, "не вдалось розібрати команду: {$command}");
        $this->assertCount(6, $argv, "ін'єкція розпалась на зайві аргументи: {$command}");
        $this->assertSame('/usr/bin/php', $argv[0]);
        $this->assertSame('/srv/module/bin/phing.phar', $argv[1]);
        $this->assertSame('-Dbranch=' . $branch, $argv[4]);
    }

    public function injectionProvider()
    {
        return [
            'quote and semicolon' => ['x" ; id ; echo "'],
            'command substitution' => ['$(id)'],
            'backtick'             => ['`id`'],
            'newline'              => ["dev\nid"],
            'pipe'                 => ['dev | id'],
            'and'                  => ['dev && id'],
            'redirect'             => ['dev > /tmp/pwned'],
            'single quote'         => ["dev' ; id ; '"],
            'benign'               => ['production'],
        ];
    }

    public function testPathToPhpIsAlsoEscaped()
    {
        $command = DeployHelper::buildDeployCommand('/srv/module', "/usr/bin/'; id; '", 'dev');
        $argv = self::shellSplit($command);

        $this->assertNotFalse($argv, "не вдалось розібрати команду: {$command}");
        $this->assertCount(6, $argv);
        $this->assertSame("/usr/bin/'; id; 'php", $argv[0]);
    }

    /**
     * @dataProvider rejectedChannelProvider
     */
    public function testGetBranchRejectsAnythingOutsideTheWhitelist($channel)
    {
        $helper = self::helperWithoutConstructor();

        $this->assertNull($helper->getBranch($channel));
    }

    public function rejectedChannelProvider()
    {
        return [
            'injection'  => ['x" ; id ; echo "'],
            'unknown'    => ['staging'],
            'empty'      => [''],
            'null'       => [null],
            'array'      => [['dev']],
            'numeric'    => [0],
            // Нестроге порівняння пропустило б це як рівне 'dev'.
            'case shift' => ['DEV'],
        ];
    }

    /**
     * @dataProvider acceptedChannelProvider
     */
    public function testGetBranchAcceptsKnownChannels($channel)
    {
        $helper = self::helperWithoutConstructor();

        $this->assertSame($channel, $helper->getBranch($channel));
    }

    public function acceptedChannelProvider()
    {
        return [
            'dev'        => ['dev'],
            'production' => ['production'],
        ];
    }

    public function testBuildChannelsAreTheOnlyAllowedValues()
    {
        $this->assertSame(['dev', 'production'], DeployHelper::BUILD_CHANNELS);
    }

    /**
     * getBranch() не торкається жодної залежності, тому створюємо помічника
     * без конструктора — інакше знадобився б увесь DI-контейнер.
     */
    private static function helperWithoutConstructor()
    {
        return (new \ReflectionClass(DeployHelper::class))->newInstanceWithoutConstructor();
    }

    /**
     * Мінімальний розбір командного рядка на аргументи за правилами sh.
     *
     * @return array|false
     */
    private static function shellSplit($command)
    {
        $args = [];
        $current = '';
        $started = false;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($char === "'") {
                $started = true;
                $close = strpos($command, "'", $i + 1);
                if ($close === false) {
                    return false;
                }
                $current .= substr($command, $i + 1, $close - $i - 1);
                $i = $close;
                continue;
            }

            // Поза лапками зворотний слеш екранує наступний символ:
            // саме так escapeshellarg() вставляє одинарну лапку — '\''.
            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    return false;
                }
                $started = true;
                $current .= $command[$i + 1];
                $i++;
                continue;
            }

            if ($char === ' ') {
                if ($started) {
                    $args[] = $current;
                    $current = '';
                    $started = false;
                }
                continue;
            }

            // Будь-який метасимвол поза лапками означає, що екранування
            // не спрацювало і шелл виконав би тут щось своє.
            if (strpos("|&;<>()\$`\n", $char) !== false) {
                return false;
            }

            $started = true;
            $current .= $char;
        }

        if ($started) {
            $args[] = $current;
        }

        return $args;
    }
}
