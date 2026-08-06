<?php

namespace Modules\OkayCMS\Rozetka;

// tests/ не входить в autoload composer'а, тож спільну базу підключаємо явно.
require_once __DIR__ . '/../FeedRelationsReadingTestCase.php';

use Modules\OkayCMS\FeedRelationsReadingTestCase;
use Okay\Modules\OkayCMS\Rozetka\Entities\RozetkaFeedsEntity;
use Okay\Modules\OkayCMS\Rozetka\Entities\RozetkaRelationsEntity;
use Okay\Modules\OkayCMS\Rozetka\Helpers\BackendRozetkaHelper;

class BackendRozetkaHelperTest extends FeedRelationsReadingTestCase
{
    protected function helperClass(): string
    {
        return BackendRozetkaHelper::class;
    }

    protected function feedsEntityClass(): string
    {
        return RozetkaFeedsEntity::class;
    }

    protected function relationsEntityClass(): string
    {
        return RozetkaRelationsEntity::class;
    }
}
