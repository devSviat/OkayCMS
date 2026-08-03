# Експорт

Експорт іде у CSV. Штатно вивантажуються всі товари, товари категорії або товари бренду;
запускається в адмінці: **Каталог → Експорт**.

Ця сторінка — про дві речі: у якому форматі виходить файл (це варто знати, якщо вигрузку
розбирає ваш скрипт) і як **модуль** додає до неї власну колонку чи критерій відбору. Уся логіка
експорту живе в `Okay\Admin\Helpers\BackendExportHelper`.

## Формат файлу

Усі шість точок експорту — товари, замовлення, користувачі, підписники, статистика і звіт —
пишуть через `Okay\Core\Export\CsvWriter`.

**UTF-8 з BOM.** BOM пишеться разом із заголовком, один раз на файл: файл накопичується частинами
між AJAX-сторінками. Без BOM Excel під Windows читає CSV в ANSI і кирилиця розсипається.

**Значення, схожі на формулу, екрануються.** Рядок, що починається з `=`, `+`, `-`, `@`,
табуляції або `CR`, отримує префікс `'`. Excel і LibreOffice виконують такі значення при
відкритті файлу — включно з DDE, — а в експорт потрапляють імена, коментарі й адреси, які вводить
покупець. Апостроф видимий у рядку формул, але не в комірці.

Якщо ваш модуль пише в експорт сам, користуйтесь тим самим writer'ом, а не `fputcsv()` напряму:

```php
use Okay\Core\Export\CsvWriter;

CsvWriter::putHeader($handle, $columnNames, $delimiter);   // лише для першої сторінки
CsvWriter::putRow($handle, $values, $delimiter);
```

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
