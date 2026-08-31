<?php

namespace Core;

use Okay\Core\Config;
use PHPUnit\Framework\TestCase;

class ConfigForkVersionTest extends TestCase
{
    public function testForkVersionDefaultIsSemver(): void
    {
        $defaults = (new \ReflectionClass(Config::class))->getDefaultProperties();

        $this->assertArrayHasKey('forkVersion', $defaults);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $defaults['forkVersion']);
    }
}
