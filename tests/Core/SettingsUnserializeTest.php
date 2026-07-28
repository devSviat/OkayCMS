<?php

namespace Core;

use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Settings values are unserialized with an allowed_classes constraint so a
 * tampered row cannot instantiate arbitrary objects. The constraint started as
 * `false`, which denies every class - including stdClass, which the discount
 * settings genuinely store: BackendDiscountsRequest writes cart_discount_sets and
 * purchase_discount_sets as arrays of (object)[...].
 *
 * With stdClass denied, those became __PHP_Incomplete_Class, DiscountsHelper read
 * null out of $set->set, and no coupon or group discount could be attached at all.
 *
 * stdClass has no methods, no destructor and no __wakeup, so allowing it is not an
 * object-injection gadget. Anything that does have such magic must stay denied.
 */
class SettingsUnserializeTest extends TestCase
{
    private function unserialize($value, $default = false)
    {
        // The method touches no state, so the constructor and its DB dependencies
        // are skipped rather than mocked.
        $settings = (new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(Settings::class))->getMethod('unserialize');
        $method->setAccessible(true);

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
