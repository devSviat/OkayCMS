<?php

namespace Modules\OkayCMS\Hotline;

// tests/ не входить в autoload composer'а, тож спільну базу підключаємо явно.
require_once __DIR__ . '/../FeedRelationsReadingTestCase.php';

use Modules\OkayCMS\FeedRelationsReadingTestCase;
use Okay\Modules\OkayCMS\Hotline\Entities\HotlineFeedsEntity;
use Okay\Modules\OkayCMS\Hotline\Entities\HotlineRelationsEntity;
use Okay\Modules\OkayCMS\Hotline\Helpers\BackendHotlineHelper;

class BackendHotlineHelperTest extends FeedRelationsReadingTestCase
{
    protected function helperClass(): string
    {
        return BackendHotlineHelper::class;
    }

    protected function feedsEntityClass(): string
    {
        return HotlineFeedsEntity::class;
    }

    protected function relationsEntityClass(): string
    {
        return HotlineRelationsEntity::class;
    }
}
