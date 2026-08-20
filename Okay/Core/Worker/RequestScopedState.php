<?php

namespace Okay\Core\Worker;

use Okay\Core\Modules\ModuleCache;
use Okay\Core\Request;
use Okay\Core\Router;
use Okay\Core\Routes\AbstractRoute;
use Okay\Core\Security\SessionNames;
use Okay\Core\UserReferer\UserReferer;

/**
 * Статика, яку доводиться скидати на межі запиту.
 *
 * Перелік навмисно короткий: якщо стан можна тримати в інстансі
 * request-scoped сервіса, це правильніша відповідь, ніж скидання. Сюди
 * потрапляє лише те, що читається статично з багатьох місць і тому мусить
 * лишитись статикою.
 *
 * StaticStateGuardTest вимагає, щоб кожна статична властивість форку була або
 * тут, або в переліку виключень із причиною.
 */
final class RequestScopedState
{
    /**
     * Клас => статичні властивості, які reset() зобов'язаний прибрати.
     *
     * @var array<class-string, string[]>
     */
    public const RESET = [
        // Логін менеджера з бекендової сесії. Найдорожча з усіх: Router
        // довіряє їй показ вимкнених мов і обхід site_work=off.
        SessionNames::class => ['adminChecked', 'adminLogin'],
        // Ім'я поточного роута і сам перелік роутів: slug частини з них
        // будується з URL, який зараз обробляється.
        Router::class => ['currentRouteName', 'routes', 'routesInitialized'],
        // Дозвіл на SQL у генерації slug і аліаси урлів переглянутих сторінок.
        AbstractRoute::class => ['useSqlToGenerate', 'routeAliases'],
        // Джерело переходу відвідувача.
        UserReferer::class => ['userReferer'],
        // Домен, протокол і підпапка, задані явно.
        Request::class => ['domain', 'protocol', 'subDir'],
        // Дволітерний кеш переліку модулів.
        ModuleCache::class => ['timeExpire', 'modules'],
    ];

    public static function reset(): void
    {
        SessionNames::resetRequestState();
        Router::resetRequestState();
        AbstractRoute::resetRequestState();
        UserReferer::resetRequestState();
        Request::resetRequestState();
        ModuleCache::flush();
    }
}
