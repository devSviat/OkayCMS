<?php

namespace Okay\Core\Update;

use Okay\Core\Modules\Module;

/**
 * Встановлені модулі, у яких версія в Init/module.json новіша за записану в
 * ok_modules — тобто їхні update_x_y_z() ще не виконані.
 *
 * Оновлення ядра їх свідомо не запускає: update_x_y_z() не обов'язково
 * ідемпотентні й відкату навколо них немає. Але без цього переліку про
 * незапущену міграцію модуля ніде не сказано — треба випадково помітити
 * кнопку на сторінці «Модулі».
 */
final class PendingModuleUpdates
{
    /**
     * @param iterable<object> $installedModules рядки ok_modules (vendor, module_name, version)
     * @return list<array{vendor: string, module_name: string, installed: string, available: string}>
     */
    public static function collect(iterable $installedModules, Module $moduleCore): array
    {
        $versionControl = $moduleCore->getVersionControl();

        $pending = [];
        foreach ($installedModules as $module) {
            $params = $moduleCore->getModuleParams($module->vendor, $module->module_name);

            // Та сама умова, що й у кнопки «оновити» на сторінці «Модулі»: без
            // ліцензії кнопки там немає, і посилання вело б у нікуди.
            if (!$params->isLicensed() || !$versionControl->lessThan($module->version, $params->getVersion())) {
                continue;
            }

            $pending[] = [
                'vendor' => (string) $module->vendor,
                'module_name' => (string) $module->module_name,
                'installed' => (string) $module->version,
                'available' => $params->getVersion(),
            ];
        }

        return $pending;
    }
}
