<?php

namespace Okay\Core;

use Okay\Core\DebugBar\DebugBar;
use Okay\Core\Http\TerminateRequest;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\OkayContainer;
use Okay\Core\Security\SessionNames;
use Psr\Log\LoggerInterface;

/**
 * Життєвий цикл запиту вітрини.
 *
 * Розділення на boot/handle/terminate потрібне воркеру: під ним boot()
 * виконується раз на процес, а межа запиту мусить бути явною. У classic mode
 * усі три викликаються поспіль, тож обидва режими ходять одним кодом.
 */
final class Kernel
{
    /** @var OkayContainer */
    private $DI;

    /** @var Config */
    private $config;

    /** @var bool */
    private $booted = false;

    public function boot(): self
    {
        if ($this->booted === true) {
            return $this;
        }

        $this->DI     = include __DIR__ . '/config/container.php';
        $this->config = $this->DI->get(Config::class);
        $this->booted = true;

        return $this;
    }

    public function getContainer(): OkayContainer
    {
        return $this->boot()->DI;
    }

    /**
     * @param float|null $startTime початок запиту; classic-точка входу знає його
     *                              раніше за нас, воркер - ні
     */
    public function handle(?float $startTime = null): void
    {
        $this->boot();
        $startTime = $startTime ?? microtime(true);

        // Сесія стартує на кожному запиті, а не в boot(): у воркері boot()
        // відпрацює один раз, і всі покупці ділили б одну сесію.
        //
        // isAdmin() строго до startFrontend(): одночасно активною може бути
        // лише одна сесія, а тут ми на мить читаємо бекендову.
        SessionNames::isAdmin();
        SessionNames::startFrontend();

        // Панель відладки вмикається з конфіга (debug_bar у config.local.php).
        if ($this->config->get('debug_bar') == true && $this->config->get('debug_mode') == true) {
            DebugBar::init();
        }
        DebugBar::startMeasure('init', 'System init');

        try {
            /** @var Router $router */
            $router = $this->DI->get(Router::class);

            // Редирект с повторяющихся слешей
            $uri = str_replace(Request::getDomainWithProtocol(), '', Request::getCurrentUrl());
            if (($destination = preg_replace('~//+~', '/', $uri, -1, $countReplace)) && $countReplace > 0) {
                Response::redirectTo($destination, 301);
            }
            $router->resolveCurrentLanguage();

            if ($this->config->get('debug_mode') == true) {
                ini_set('display_errors', 'on');
                error_reporting(E_ALL);
            }

            /** @var Response $response */
            $response = $this->DI->get(Response::class);

            /** @var Request $request */
            $request = $this->DI->get(Request::class);
            // Установим время начала выполнения скрипта
            $request->setStartTime($startTime);

            if (isset($_GET['logout'])) {
                unset($_SESSION['admin']);
                unset($_SESSION['modules_request_timeout']);
                unset($_SESSION['support_request_timeout']);
                unset($_SESSION['last_version_data']);
                setcookie('admin_login', '', [
                    'expires'  => time() - 100,
                    'path'     => '/',
                    'secure'   => SessionNames::isHttps(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                $response->redirectTo($request->getRootUrl());
            }

            /** @var Modules $modules */
            $modules = $this->DI->get(Modules::class);
            DebugBar::stopMeasure('init');
            $modules->startEnabledModules();

            $router->run();

            if ($response->getContentType() == RESPONSE_HTML) {
                // Отладочная информация
                print "<!--\r\n";
                $timeEnd = microtime(true);
                $execTime = $timeEnd - $startTime;

                if (function_exists('memory_get_peak_usage')) {
                    print "memory peak usage: " . memory_get_peak_usage() . " bytes\r\n";
                }
                print "page generation time: " . $execTime . " seconds\r\n";
                print "-->";
            }
        } catch (TerminateRequest $terminate) {
            // Відповідь уже віддана: редірект, 304 або рання відсічка.
        } catch (\Exception $e) {

            /** @var LoggerInterface $logger */
            $logger = $this->DI->get(LoggerInterface::class);

            $message = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            if ($this->config->get('debug_mode') == true) {
                print $message;
            } else {
                $logger->critical($message);
            }
        }
    }

    /**
     * Межа запиту. У classic mode тут майже нічого не відбувається - процес
     * однаково помре, - але код мусить бути той самий, інакше воркер лишиться
     * єдиним місцем, де прибирання взагалі перевіряється.
     */
    public function terminate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Закриття сесії не забирає ідентифікатор із модуля сесій: без явного
        // скидання наступний session_start() перевикористав би сесію
        // попереднього покупця, бо $_COOKIE скидається, а це - ні.
        session_id('');

        if ($this->booted === true) {
            $this->DI->resetRequestScoped();
        }
    }
}
