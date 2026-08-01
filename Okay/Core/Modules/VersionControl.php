<?php

namespace Okay\Core\Modules;

class VersionControl
{
    public function versionCompare($version1, $version2): ?int
    {
        // ok_modules.version буває NULL (модуль стоїть, а версія не записана),
        // а version_compare() від PHP 8.1 сварить на null.
        return version_compare((string)$version1, (string)$version2);
    }

    public function greaterThan($version1, $version2): bool
    {
        return $this->versionCompare($version1, $version2) === 1;
    }

    public function lessThan($version1, $version2): bool
    {
        return $this->versionCompare($version1, $version2) === -1;
    }

    public function equal($version1, $version2): bool
    {
        return $this->versionCompare($version1, $version2) === 0;
    }
}