<?php


namespace Okay\Modules\OkayCMS\AutoDeploy\Helpers;


use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Request;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\AutoDeploy\Entities\MigrationsEntity;

class DeployHelper
{
    /** Канали збірки, які модуль уміє обслуговувати. Інших значень бути не може. */
    const BUILD_CHANNELS = ['dev', 'production'];

    private $request;
    private $settings;
    private $database;
    private $entityFactory;
    
    private $migrationDir;
    
    public function __construct(Request $request, Settings $settings, Database $database, EntityFactory $entityFactory)
    {
        $this->request = $request;
        $this->settings = $settings;
        $this->database = $database;
        $this->entityFactory = $entityFactory;
        
        $this->migrationDir = dirname(__DIR__) . '/migrations/';
    }

    public function executeHook($channel): bool
    {
        $requestBody = $this->request->post();
        $requestBody = json_decode($requestBody);
        
        $branch = $this->getBranch($channel);

        $currentChannel = $this->settings->get('deploy_build_channel');
        if ($channel != $currentChannel) {
            return false;
        }
        
        // В случае удаления ветки, new будет равен null
        if ($requestBody === null || $requestBody->push->changes[0]->new === null) {
            $this->settings->set('deploy_last_status_text', date("d.m.Y H:i:s") . PHP_EOL . 'Empty request from bitbucket');
            return false;
        }

        // Если прилетел хук, но пушили не в ветку которую мы "слушаем", phing запускать не нужно
        if ($requestBody->push->changes[0]->new->name != $branch) {
            return false;
        }
        
        $this->updateProject($branch);
        return true;
    }
    
    public function updateProject($branch)
    {
        if (!$pathToPhp = $this->settings->get('path_to_php')) {
            $constants = get_defined_constants();
            if (isset($constants['PHP_BINDIR'])) {
                $pathToPhp = rtrim($constants['PHP_BINDIR'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }
        
        $command = self::buildDeployCommand(dirname(__DIR__), $pathToPhp, $branch);

        exec($command, $output);
        $deployLog = date("d.m.Y H:i:s")
            . PHP_EOL
            . implode(PHP_EOL, $output);
        $this->settings->set('deploy_last_status_text', $deployLog);
    }

    /**
     * Складання командного рядка деплою.
     *
     * $branch приходить із налаштування deploy_build_channel, $pathToPhp — із
     * налаштувань модуля. Раніше обидва вставлялись у рядок як є, всередині
     * подвійних лапок: одна лапка у значенні закривала -Dbranch="..." і все
     * після неї шелл виконував як окрему команду.
     *
     * @param string $dir корінь модуля
     * @param string $pathToPhp каталог з бінарником php (може бути порожнім)
     * @param string $branch гілка збірки
     * @return string
     */
    public static function buildDeployCommand($dir, $pathToPhp, $branch)
    {
        return escapeshellarg($pathToPhp . 'php')
            . ' ' . escapeshellarg($dir . '/bin/phing.phar')
            . ' -f ' . escapeshellarg($dir . '/build.xml')
            . ' -Dbranch=' . escapeshellarg((string) $branch)
            . ' -Dphp_path=' . escapeshellarg((string) $pathToPhp);
    }

    /**
     * Метод выполняет все новые миграции
     * @throws \Exception
     */
    public function executeMigrations()
    {
        /** @var MigrationsEntity $migrationsEntity */
        $migrationsEntity = $this->entityFactory->get(MigrationsEntity::class);
        
        if ($newMigrations = $this->getNewMigrations()) {
            foreach ($newMigrations as $migration) {
                $this->database->restore($migration['full_path']);
                $migrationsEntity->add(['name' => $migration['name']]);
            }
        }
    }
    
    public function getNewMigrations()
    {
        $newMigrations = [];
        /** @var MigrationsEntity $migrationsEntity */
        $migrationsEntity = $this->entityFactory->get(MigrationsEntity::class);

        $migrationsCount = $migrationsEntity->count();
        
        $alreadyExecuted = $migrationsEntity->cols(['name'])->find([
            'limit' => $migrationsCount,
        ]);
        
        foreach (glob($this->migrationDir . "*.up.sql") as $path) {
            $file = pathinfo($path, PATHINFO_BASENAME);
            if (!in_array($file, $alreadyExecuted)) {
                $newMigrations[] = [
                    'full_path' => $path,
                    'name' => $file,
                ];
            }
        }
        return ExtenderFacade::execute(__METHOD__, $newMigrations, func_get_args());
    }
    
    public function createMigration($name)
    {
        $migrationName = date("YmdHis") . (empty($name) ? '' : '_'.$name) . ".up.sql";
        fclose(fopen($this->migrationDir . $migrationName, "w"));
        return ExtenderFacade::execute(__METHOD__, $migrationName, func_get_args());
    }
    
    public function getBranch($channel)
    {
        $branch = in_array($channel, self::BUILD_CHANNELS, true) ? $channel : null;

        return ExtenderFacade::execute(__METHOD__, $branch, func_get_args());
    }

    public function updateModules() : bool
    {
        $SL = ServiceLocator::getInstance();

        $entityFactory = $SL->getService('Okay\Core\EntityFactory');
        $modulesEntity = $entityFactory->get('Okay\Entities\ModulesEntity');
        $installer = $SL->getService('Okay\Core\Modules\Installer');

        if (!$modules = $modulesEntity->find()) {
            return false;
        }
        foreach ($modules as $module) {
            $installer->update((int)$module->id);
        }

        return true;
    }
}