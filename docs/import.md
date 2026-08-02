# Імпорт

З CSV-файлів імпортуються товари, категорії та властивості товарів. Сам імпорт запускається в
адмінці: **Каталог → Імпорт**, там же файл зіставляється з полями магазину.

Ця сторінка — про інше: як **модуль** додає до імпорту власне поле. Уся логіка імпорту живе в
`Okay\Admin\Helpers\BackendImportHelper`.

## Своє поле в імпорті

### 1. Показати поле в зіставленні колонок

Перелік полів, який виводиться при запуску імпорту, розширюється через блок дизайну
`import_fields_association`:

```php
public function init()
{
    $this->addBackendBlock('import_fields_association', 'import_fields_association.tpl');
}
```

### 2. Прочитати значення з файлу

Розширенням `BackendImportHelper::parseProductData()` (для товару) або `parseVariantData()`
(для варіанта). Другим аргументом приходить рядок CSV:

```php
public function extendParseProductData($product, $itemFromCsv)
{
    if (!empty($itemFromCsv['supplier'])) {
        // …
    }

    return $product;
}
```

Обидва методи оголошені `private`, і це не заважає: тригер розширення спрацьовує зсередини
самого методу.

### 3. Не дати перетворити поле на властивість товару

Колонка, якої система не знає, потрапляє в товар як нова властивість. Щоб цього не сталось,
поле треба оголосити в `BackendImportHelper::getModulesColumnsNames()`:

```php
public function extendModulesColumnsNames($modulesColumnsNames)
{
    $modulesColumnsNames['supplier'] = 'supplier';
    return $modulesColumnsNames;
}
```

### 4. Доробити після імпорту

Розширенням `BackendImportHelper::afterImportProductProcedure()` — воно отримує товар,
варіант і перелік категорій. Метод нічого не повертає, тож це **чергове** розширення
([modules/extenders.md](modules/extenders.md#чергове-розширення)).

## Реальний приклад

`OkayCMS/NovaposhtaCost` додає в імпорт об'єм варіанта: блок `import_fields_association`,
розширення `parseVariantData` і поле в `getModulesColumnsNames()`.

Експорт — [export.md](export.md).
