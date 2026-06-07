<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendSettingsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The constructor-injected $licenseModulesTemplates dependency used to be
 * assigned without a property declaration, triggering the PHP 8.2 dynamic
 * property deprecation; it must now be declared.
 */
class BackendSettingsHelperTest extends TestCase
{
    public function testDeclaresLicenseModulesTemplates(): void
    {
        $this->assertTrue(
            (new ReflectionClass(BackendSettingsHelper::class))->hasProperty('licenseModulesTemplates'),
            'BackendSettingsHelper::$licenseModulesTemplates must be a declared property (was assigned dynamically).'
        );
    }
}
