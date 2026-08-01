<?php

namespace Core;

use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Значення налаштувань розсеріалізовуються з обмеженням allowed_classes, щоб
 * підмінений рядок не міг створити довільний обʼєкт. Дозволено лише stdClass —
 * його справді зберігають налаштування знижок, а заборона перетворювала їх на
 * __PHP_Incomplete_Class і жодна знижка не застосовувалась.
 *
 * stdClass не має методів, деструктора й __wakeup, тож не є гаджетом для
 * обʼєктної інʼєкції. Усе, що таку магію має, лишається забороненим.
 */
class SettingsUnserializeTest extends TestCase
{
    private function unserialize($value, $default = false)
    {
        // The method touches no state, so the constructor and its DB dependencies
        // are skipped rather than mocked.
        $settings = (new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(Settings::class))->getMethod('unserialize');

        return $method->invoke($settings, $value, $default);
    }

    public function testTheStoredDiscountSetSurvivesAsARealObject(): void
    {
        // The exact payload the admin writes, taken from a live ok_settings row.
        $stored = 'a:1:{i:0;O:8:"stdClass":2:{s:3:"set";s:17:"$<ok_coup $<ok_gr";s:7:"partial";b:1;}}';

        $sets = $this->unserialize($stored);

        $this->assertIsArray($sets);
        $this->assertInstanceOf(\stdClass::class, $sets[0]);
        $this->assertSame('$<ok_coup $<ok_gr', $sets[0]->set);
        $this->assertTrue($sets[0]->partial);
    }

    public function testPlainArraysStillRoundTrip(): void
    {
        $this->assertSame(['a', 'b'], $this->unserialize(serialize(['a', 'b'])));
    }

    public function testANonSerializedValueFallsBackToTheDefault(): void
    {
        $this->assertSame('d.m.Y', $this->unserialize('d.m.Y', 'd.m.Y'));
    }

    public function testAClassWithMagicIsStillRefused(): void
    {
        // ArrayObject has __wakeup/__unserialize and is the shape of a gadget; it
        // must not come back as a real instance.
        $payload = serialize(new \ArrayObject(['x']));

        $this->assertNotInstanceOf(\ArrayObject::class, $this->unserialize($payload));
    }
}
