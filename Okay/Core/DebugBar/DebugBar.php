<?php

namespace Okay\Core\DebugBar;

use Aura\Sql\ExtendedPdo;
use DebugBar\Bridge\Monolog\MonologCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar as LibDebugBar;
use Monolog\Logger;
use Okay\Core\DebugBar\DataCollectors\ConfigCollector;
use Okay\Core\DebugBar\DataCollectors\PDOCollector;
use Okay\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

class DebugBar
{
    /** @var LibDebugBar */
    private static $debugBar;

    /** @var ServiceLocator */
    private static $serviceLocator;

    /**
     * Значення конфіга, прочитані до init().
     *
     * index.php мусить знати debug_bar, перш ніж вмикати панель, тож увесь основний
     * конфіг завантажується раніше за колектори — без буфера вкладка Config показувала
     * тільки те, що дочитувалось потім (конфіги модулів).
     *
     * @var list<array{name: string, value: mixed, source: string}>
     */
    private static array $bufferedConfigValues = [];

    public static function init()
    {
        if (self::$debugBar === null && class_exists(LibDebugBar::class)) {
            self::$debugBar = new LibDebugBar();
            self::$serviceLocator = ServiceLocator::getInstance();

            self::initCollectors();

            /** @var Logger $logger */
            $logger = self::$serviceLocator->getService(LoggerInterface::class);
            self::addLogger($logger);
        }
    }

    private static function initCollectors()
    {
        if (!is_null(self::$debugBar)) {
            self::addCollector(new PhpInfoCollector());
            self::addCollector(new MessagesCollector());
            self::addCollector(new RequestDataCollector());
            self::addCollector(new MemoryCollector());

            self::addCollector(new TimeDataCollector());
            self::addCollector(new ConfigCollector());
            self::addCollector(new MonologCollector(null, Logger::DEBUG, true, 'system_log'));

            /** @var ExtendedPdo $extendedPdo */
            $extendedPdo = self::$serviceLocator->getService(ExtendedPdo::class);
            $traceablePdo = new TraceablePDO($extendedPdo);
            DebugBar::addCollector(new PDOCollector($traceablePdo));

            foreach (self::$bufferedConfigValues as $buffered) {
                self::$debugBar['config']->set($buffered['name'], $buffered['value'], $buffered['source']);
            }
            self::$bufferedConfigValues = [];
        }
    }

    public static function addCollector(DataCollectorInterface $dataCollector)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar->addCollector($dataCollector);
        }
    }

    public static function getCollector($name)
    {
        if (!is_null(self::$debugBar)) {
            return self::$debugBar->getCollector($name);
        }
        return null;
    }

    public static function stackData()
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar->stackData();
        }
    }

    public static function addLogger(Logger $logger)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['system_log']->addLogger($logger);
        }
    }

    public static function setConfigValue($name, $value, $source)
    {
        if (is_null(self::$debugBar)) {
            self::$bufferedConfigValues[] = ['name' => $name, 'value' => $value, 'source' => $source];
            return;
        }

        self::$debugBar['config']->set($name, $value, $source);
    }

    public static function getRenderer()
    {
        if (!is_null(self::$debugBar)) {
            return self::$debugBar->getJavascriptRenderer();
        }
        return null;
    }

    /**
     * Inline-асети панелі (стилі й скрипти symfony/var-dumper) готовими тегами.
     *
     * JavascriptRenderer::render() їх не виводить, а у файли вони не потрапляють —
     * HtmlDataFormatter віддає їх лише як inline_css/inline_js.
     */
    public static function getInlineAssets(): string
    {
        if (($renderer = self::getRenderer()) === null) {
            return '';
        }

        $assets = $renderer->getAssets(null);
        $html = '';

        foreach ($assets['inline_css'] as $content) {
            $html .= '<style>' . $content . '</style>' . "\n";
        }
        foreach ($assets['inline_js'] as $content) {
            $html .= '<script type="text/javascript">' . $content . '</script>' . "\n";
        }
        foreach ($assets['inline_head'] as $content) {
            $html .= $content . "\n";
        }

        return $html;
    }

    public static function startMeasure($name, $label = null, $collector = null, $group = null)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['time']->startMeasure($name, $label, $collector, $group);
        }
    }

    public static function stopMeasure($name, $params = [])
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['time']->stopMeasure($name, $params);
        }
    }

    public static function hasStartedMeasure($name)
    {
        if (!is_null(self::$debugBar)) {
            return self::$debugBar['time']->hasStartedMeasure($name);
        }
        return null;
    }

    public static function addMessage($message, $label = 'info')
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->addMessage($message, $label);
        }
    }

    public static function error($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->error($message);
        }
    }

    public static function emergency($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->emergency($message);
        }
    }

    public static function alert($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->alert($message);
        }
    }

    public static function critical($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->critical($message);
        }
    }

    public static function warning($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->warning($message);
        }
    }

    public static function notice($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->notice($message);
        }
    }

    public static function info($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->info($message);
        }
    }

    public static function debug($message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->debug($message);
        }
    }

    public static function log($level, $message)
    {
        if (!is_null(self::$debugBar)) {
            self::$debugBar['messages']->log($level, $message);
        }
    }

    public static function startExtensionExecution($trigger, $extension)
    {
        if (!is_null(self::$debugBar)) {
            $vendorName = preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$1', $extension->class);
            $moduleName = preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$2', $extension->class);

            self::startMeasure("$vendorName/$moduleName", "Module $vendorName/$moduleName", null, "$vendorName/$moduleName");
        }
    }

    public static function finishExtensionExecution($trigger, $extension)
    {
        if (!is_null(self::$debugBar)) {
            $vendorName = preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$1', $extension->class);
            $moduleName = preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$2', $extension->class);

            self::stopMeasure("$vendorName/$moduleName", ['Extension' => "$trigger -> $extension->class::$extension->method"]);
        }
    }

    public static function startDesignBlockFetch($blockTplFile)
    {
        if (!is_null(self::$debugBar)) {
            $vendorName = preg_replace('~Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$1', $blockTplFile);
            $moduleName = preg_replace('~Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$2', $blockTplFile);

            self::startMeasure("$vendorName/$moduleName", "Module $vendorName/$moduleName", null, "$vendorName/$moduleName");
        }
    }

    public static function finishDesignBlockFetch($blockName, $blockTplFile)
    {
        if (!is_null(self::$debugBar)) {
            $vendorName = preg_replace('~Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$1', $blockTplFile);
            $moduleName = preg_replace('~Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$2', $blockTplFile);

            self::stopMeasure("$vendorName/$moduleName", ['Design block' => "$blockName -> ".pathinfo($blockTplFile, PATHINFO_FILENAME)]);
        }
    }
}