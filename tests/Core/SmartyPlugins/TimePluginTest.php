<?php

namespace Core\SmartyPlugins;

use Okay\Core\SmartyPlugins\Plugins\Time;
use PHPUnit\Framework\TestCase;

/**
 * `{$m->last_activity|time}` на менеджері, який ще жодного разу не заходив,
 * передавав null у strtotime() - і PHP 8 друкував Deprecated просто в HTML
 * сторінки менеджерів. Розбір дати має збігатися з Date.
 *
 * convertDeprecationsToExceptions у phpunit.xml робить ці тести чутливими:
 * повернення null у strtotime() завалить їх само собою.
 */
class TimePluginTest extends TestCase
{
    public function testNullRendersCurrentTimeWithoutDeprecation(): void
    {
        $this->assertSame(date('H:i'), (new Time())->run(null));
    }

    public function testEpochSurvivesTheFalsyTimestamp(): void
    {
        $this->assertSame(date('H:i', 0), (new Time())->run(0));
    }

    public function testParseableDateIsFormatted(): void
    {
        $this->assertSame('14:35', (new Time())->run('2026-08-01 14:35:00'));
    }

    public function testCustomFormatIsHonoured(): void
    {
        $this->assertSame('14:35:07', (new Time())->run('2026-08-01 14:35:07', 'H:i:s'));
    }

    public function testUnparseableInputComesBackUnformatted(): void
    {
        $this->assertSame('не дата', (new Time())->run('не дата'));
    }
}
