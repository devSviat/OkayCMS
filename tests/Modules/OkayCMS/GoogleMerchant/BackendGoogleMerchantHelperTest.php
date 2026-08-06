<?php

namespace Modules\OkayCMS\GoogleMerchant;

// tests/ не входить в autoload composer'а, тож спільну базу підключаємо явно.
require_once __DIR__ . '/../FeedRelationsReadingTestCase.php';

use Modules\OkayCMS\FeedRelationsReadingTestCase;
use Okay\Modules\OkayCMS\GoogleMerchant\Entities\GoogleMerchantFeedsEntity;
use Okay\Modules\OkayCMS\GoogleMerchant\Entities\GoogleMerchantRelationsEntity;
use Okay\Modules\OkayCMS\GoogleMerchant\Helpers\BackendGoogleMerchantHelper;

class BackendGoogleMerchantHelperTest extends FeedRelationsReadingTestCase
{
    protected function helperClass(): string
    {
        return BackendGoogleMerchantHelper::class;
    }

    protected function feedsEntityClass(): string
    {
        return GoogleMerchantFeedsEntity::class;
    }

    protected function relationsEntityClass(): string
    {
        return GoogleMerchantRelationsEntity::class;
    }
}
