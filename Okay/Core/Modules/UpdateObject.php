<?php


namespace Okay\Core\Modules;


class UpdateObject
{
    private static $objects = [];

    public function register($alias, $permission, $entityClassName)
    {
        $object = new \stdClass();
        $object->permission = $permission;
        $object->entityName = $entityClassName;

        // Повторна реєстрація того самого - не конфлікт: бутстрап модулів
        // проходить на кожному запиті. Конфлікт - це коли аліас той самий,
        // а стоїть за ним інше.
        if (isset(self::$objects[$alias]) && self::$objects[$alias] != $object) {
            throw new \Exception("Alias \"{$alias}\" already exists");
        }

        self::$objects[$alias] = $object;
    }

    public function getObjects()
    {
        return self::$objects;
    }

    public function getByAlias($alias)
    {
        if (!in_array($alias, array_keys(self::$objects))) {
            return false;
        }

        return self::$objects[$alias];
    }
}