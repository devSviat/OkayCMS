# Експорт

Експорт іде у CSV. Штатно вивантажуються всі товари, товари категорії або товари бренду;
запускається в адмінці: **Каталог → Експорт**.

Ця сторінка — про те, як **модуль** додає до вивантаження власну колонку чи критерій відбору.
Уся логіка експорту живе в `Okay\Admin\Helpers\BackendExportHelper`.

## Своя колонка в експорті

Розширенням `BackendExportHelper::getColumnsNames()`:

```php
public function extendExportColumnsNames($columnsNames)
{
    $columnsNames['supplier'] = 'Supplier';
    return $columnsNames;
}
```

Значення для варіанта дописуються розширенням `prepareVariantsData()`.

## Свій критерій відбору

Два кроки.

**1. Показати вибір в адмінці.** Розширенням `getCategoriesForExportFilter()` — у ньому
передаєте в дизайн потрібну змінну.

**2. Додати умову у вибірку.** `setUp()` повертає масив `[$filter, $page]`, тож нульовий
елемент — це фільтр товарів:

```php
public function extendSetUp($array)
{
    $supplierId = /* … */;

    $array[0] = $array[0] + ['supplier_id' => $supplierId];
    return $array;
}
```

Щоб доданий ключ фільтра щось означав, для `ProductsEntity` має існувати відповідний фільтр —
[entities.md](entities.md#фільтри) і
[modules/init-reference.md](modules/init-reference.md#registerentityfilter).

Імпорт — [import.md](import.md).
