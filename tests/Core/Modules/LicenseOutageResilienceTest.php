<?php

namespace Core\Modules;

use Okay\Core\Modules\LicenseModulesTemplates;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Аварія на боці маркетплейса не має класти чужі робочі магазини.
 *
 * Це наявна поведінка ядра, і тест лише закріплює її на час робіт із паузами,
 * таймаутами та кешем: жодна з них не має права побічно змінити відповіді
 * нижче. Змінювати їх свідомо - окреме рішення, а не наслідок оптимізації.
 */
class LicenseOutageResilienceTest extends TestCase
{
    public function testOutageKeepsModulesRunning(): void
    {
        $license = $this->makeInitializedWithoutLicense();

        $this->assertTrue($license->isLicensedModule('OkayCMS', 'AnyModule'));
    }

    public function testOutageKeepsTemplateWorking(): void
    {
        $license = $this->makeInitializedWithoutLicense();

        $this->assertTrue($license->isLicensedTemplate());
    }

    public function testOutageKeepsTemplateOfficial(): void
    {
        $license = $this->makeInitializedWithoutLicense();

        $this->assertTrue($license->isOfficialTemplate());
    }

    /**
     * Стан після невдалої спроби: ініціалізація відбулась, ліцензії немає.
     *
     * Об'єкт створюється без конструктора: жодна з трьох перевірок нижче не
     * звертається ні до бази, ні до конфігу, а стаб Database успадковує
     * справжній __destruct(), який падає на нульовому з'єднанні.
     */
    private function makeInitializedWithoutLicense(): LicenseModulesTemplates
    {
        $reflection = new ReflectionClass(LicenseModulesTemplates::class);
        $license = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('isInitialized')->setValue($license, true);
        $reflection->getProperty('licenseDTO')->setValue($license, null);

        return $license;
    }
}
