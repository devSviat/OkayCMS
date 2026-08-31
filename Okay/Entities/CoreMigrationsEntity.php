<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;

class CoreMigrationsEntity extends Entity
{
    protected static $fields = [
        'id',
        'name',
        'applied_at',
    ];

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = '__core_migrations';
    protected static $tableAlias = 'cmig';
}
