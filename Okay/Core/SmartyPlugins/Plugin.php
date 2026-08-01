<?php


namespace Okay\Core\SmartyPlugins;


use Okay\Core\Design;
use Okay\Core\Modules\Module;

abstract class Plugin
{
    public const TYPE_FUNCTION = 'function';
    public const TYPE_MODIFIER = 'modifier';

    /**
     * Тип плагіна за його базовим класом. Одне правило на обидва шляхи мокування
     * плагінів модулів - раніше вони вирішували це по-різному, і розбіжність рано
     * чи пізно вилізла б. Плагін під не тим типом лишає тег
     * незареєстрованим, а Smarty 5 на це кидає помилку компіляції.
     *
     * is_subclass_of(), а не порівняння прямого батька: проміжний клас у ієрархії
     * інакше лишив би плагін без типу взагалі.
     *
     * @param object|string $plugin обʼєкт плагіна або його FQCN
     */
    public static function resolveType($plugin): ?string
    {
        if (is_subclass_of($plugin, Func::class)) {
            return self::TYPE_FUNCTION;
        }

        if (is_subclass_of($plugin, Modifier::class)) {
            return self::TYPE_MODIFIER;
        }

        return null;
    }

    final public function register(Design $design, Module $module)
    {
        $reflector = new \ReflectionClass($this);
        
        if (!empty($this->tag)) {
            $tag = $this->tag;
        } else {
            $tag = strtolower($reflector->getShortName());
        }
        
        if (!$reflector->hasMethod('run')) {
            throw new \Exception('smarty plugin not exists!! Okay\Core\Plugins\Plugin');
        }
        
        // Тут instanceof, а не resolveType(): для обʼєкта вони рівносильні, але
        // instanceof звужує тип $this, і без цього PHPStan не бачить run().
        if ($this instanceof Modifier) {
            $design->registerPlugin(self::TYPE_MODIFIER, $tag, function(...$params) use ($design, $module) {
                if ($module->isModuleClass(static::class)) {
                    $design->setModuleDir(static::class);

                    $result = call_user_func_array([$this, 'run'], $params);
                    $design->rollbackTemplatesDir();
                    return $result;
                }

                return call_user_func_array([$this, 'run'], $params);
            });
        } elseif ($this instanceof Func) {
            $design->registerPlugin(self::TYPE_FUNCTION, $tag, function($params, $smarty = null) use ($design, $module) {
                if ($module->isModuleClass(static::class)) {
                    $design->setModuleDir(static::class);

                    $result = $this->run($params, $smarty);
                    $design->rollbackTemplatesDir();
                    return $result;
                }

                return $this->run($params, $smarty);
            });
        } else {
            throw new \Exception('smarty plugin bad instanceof!! Okay\Core\Plugins\Plugin');
        }
    }
}