<?php

namespace Okay\Core\Console\Commands\Module;

use Okay\Core\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'module:create', description: 'Creates a file structure for a new module.')]
class ModuleCreateCommand extends Command
{
    private $modulesDirectory;

    protected function configure(): void
    {
        $this->setHelp('This command allows you to create a file structure for a new module.');

        $this->modulesDirectory = dirname(__DIR__, 4).'/Modules';
    }

    protected function handle(): int
    {
        $vendor = $this->getVendorName();
        $module = $this->getModuleName($vendor);

        if (!$this->createModuleFiles($vendor, $module)) {
            $this->output->writeln(sprintf(
                '<error>Could not create the module files in %s/%s/%s</error>',
                $this->modulesDirectory,
                $vendor,
                $module
            ));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getVendorName(): string
    {
        $vendors = array_diff(scandir($this->modulesDirectory), ['.', '..']);

        $selectOptions = array_merge($vendors, ['[Create new]']);
        array_unshift($selectOptions, '');
        unset($selectOptions[0]);

        $vendor = $this->askChoice('Select vendor:', $selectOptions);

        if ($vendor === '[Create new]') {
            do {
                $vendor = $this->ask('Enter vendor name: ', false);
                if (!$vendor) {
                    $this->output->writeln('<error>Please enter module name<error>');
                }

                $vendor = $this->formatCamelCase($vendor);

                $valid = preg_match('/^[A-Z][a-zA-Z0-9]*$/', $vendor);
                if (!$valid) {
                    $this->output->writeln('<error>The name is invalid. Please use only [a-Z, 0-9], first character must be letter.<error>');
                }

                $exist = array_search(strtolower($vendor), array_map('strtolower', $vendors));
                if ($exist) {
                    $this->output->writeln('<error>Vendor already exist<error>');
                }
            } while (!$vendor || !$valid || $exist);
        }
        return $vendor;
    }

    private function getModuleName(string $vendor): string
    {
        if (is_dir($this->modulesDirectory.'/'.$vendor)) {
            $modules = array_diff(scandir($this->modulesDirectory.'/'.$vendor), ['.', '..']);
        } else {
            $modules = [];
        }

        do {
            $module = $this->ask('Enter module name: ', false);
            if (!$module) {
                $this->output->writeln('<error>Please enter module name<error>');
            }

            $module = $this->formatCamelCase($module, 'camel');

            $valid = preg_match('/^[A-Z][a-zA-Z0-9]*$/', $module);
            if (!$valid) {
                $this->output->writeln('<error>The name is invalid. Please use only [a-Z, 0-9], first character must be letter.<error>');
            }

            $exist = array_search(strtolower($module), array_map('strtolower', $modules));
            if ($exist) {
                $this->output->writeln('<error>Module already exist<error>');
            }
        } while (!$module || !$valid || $exist);

        return $module;
    }

    private function formatCamelCase(string $name): string
    {
        $name = ucwords($name, ' _');
        return preg_replace('/[ |_]+/', '', $name);
    }

    private function formatSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z]+)/', '$1_$2', $name));
    }

    private function createModuleFiles(string $vendor, string $module): bool
    {
        $vendorDir = $this->modulesDirectory.'/'.$vendor;
        if (!is_dir($vendorDir) && !@mkdir($vendorDir, 0777, true) && !is_dir($vendorDir)) {
            return false;
        }

        $moduleDir = $this->modulesDirectory.'/'.$vendor.'/'.$module;
        $replacements = [
            '%namespace_vendor%' => $vendor,
            '%namespace_module%' => $module,
            '%permission%' => $this->formatSnakeCase($vendor).'__'.$this->formatSnakeCase($module),
            '%lang%' => $this->formatSnakeCase($vendor).'__'.$this->formatSnakeCase($module),
        ];

        $this->recursiveCopy(__DIR__.'/ModuleCreateCommand/module', $moduleDir, $replacements);

        // Каркас, недоскладений мовчки, гірший за помилку: модуль виглядає створеним,
        // а частини файлів у ньому немає.
        return is_file($moduleDir.'/Init/Init.php');
    }

    private function recursiveCopy(string $source, string $destination, array $replacements): void
    {
        if (!is_dir($destination) && !@mkdir($destination, 0777, true) && !is_dir($destination)) {
            return;
        }

        $dir = opendir($source);
        while(( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($source . '/' . $file) ) {
                    $this->recursiveCopy($source .'/'. $file, $destination .'/'. $file, $replacements);
                } else {
                    $content = file_get_contents($source .'/'. $file);
                    $content = str_replace(array_keys($replacements), array_values($replacements), $content);

                    if (mb_substr($file, -4, 4) === 'tplm') {
                        $file = mb_substr($file, 0, -5);
                    }

                    file_put_contents($destination .'/'. $file, $content);
                }
            }
        }
        closedir($dir);
    }
}