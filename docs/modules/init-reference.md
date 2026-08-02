# Довідник `AbstractInit`

Усі методи, які `Init/Init.php` може викликати. Клас — `Okay\Core\Modules\AbstractInit`.

Позначка **`install()`** / **`init()`** каже, у якому методі метод має сенс. Викликати
міграцію в `init()` означає ходити в базу на кожному запиті; реєструвати розширення в
`install()` означає, що воно спрацює один раз і більше ніколи.

## Зміст

| Задача | Метод |
| ------ | ----- |
| Таблиці й поля | [migrateEntityTable](#migrateentitytable), [migrateCustomTable](#migratecustomtable), [migrateEntityField](#migrateentityfield) |
| Поля й фільтри чужих сутностей | [registerEntityField](#registerentityfield), [registerEntityAdditionalField](#registerentityadditionalfield), [registerEntityFilter](#registerentityfilter), [setDefaultOrderFields](#setdefaultorderfields), [registerEntityLangInfo](#registerentitylanginfo) |
| Розширення чужої логіки | [registerChainExtension](#registerchainextension), [registerQueueExtension](#registerqueueextension) |
| Адмінка | [setBackendMainController](#setbackendmaincontroller), [registerBackendController](#registerbackendcontroller), [addPermission](#addpermission), [addBackendControllerPermission](#addbackendcontrollerpermission), [extendBackendMenu](#extendbackendmenu), [addFastMenuItem](#addfastmenuitem), [extendUpdateObject](#extendupdateobject), [setSystem](#setsystem) |
| Блоки дизайну | [addBackendBlock](#addbackendblock), [addFrontBlock](#addfrontblock) |
| Інше | [setModuleType](#setmoduletype), [addResizeObject](#addresizeobject), [registerSchedule](#registerschedule), [registerPurchaseDiscountSign / registerCartDiscountSign](#знижки) |

---

## Таблиці й поля

### `migrateEntityTable`

```php
protected function migrateEntityTable($entityClassName, array $fields)
```

**`install()`.** Створює таблицю сутності (і мовну таблицю, якщо сутність її оголошує). Ім'я
таблиці береться з класу сутності. Деталі й `EntityField` — [migrations.md](migrations.md).

### `migrateCustomTable`

```php
protected function migrateCustomTable($tableName, array $fields)
```

**`install()`.** Створює таблицю за іменем — переважно для таблиць зв'язків. Префікс `__`
додається автоматично.

### `migrateEntityField`

```php
protected function migrateEntityField($entityClassName, EntityField $field)
```

**`install()`.** Додає одне поле до наявної таблиці — своєї або чужої. Якщо поле вже є, але
відрізняється типом, обнулюваністю чи значенням за замовчуванням, воно змінюється.

```php
$this->migrateEntityField(VariantsEntity::class, (new EntityField('volume'))->setTypeDecimal('10,5'));
```

## Поля й фільтри чужих сутностей

### `registerEntityField`

```php
protected function registerEntityField($entityClassName, $fieldName, $isLang = false)
```

**`init()`.** Повідомляє ORM, що в сутності є таке поле — щоб воно потрапляло у вибірку й
фільтри. **У базу нічого не додає**: колонку створює `migrateEntityField()` в `install()`.
Типова пара:

```php
public function install()
{
    $this->migrateEntityField(VariantsEntity::class, (new EntityField('volume'))->setTypeDecimal('10,5'));
}

public function init()
{
    $this->registerEntityField(VariantsEntity::class, 'volume');
}
```

`$isLang` порівнюється строго з `true` — передавати `1` немає сенсу.

### `registerEntityAdditionalField`

```php
protected function registerEntityAdditionalField($entityClassName, $fieldName)
```

**`init()`.** Додаткове поле, якому не обов'язково відповідає колонка: результат окремого
запиту, дописаний до об'єкта як властивість. У жодному модулі репозиторію не використовується.

### `registerEntityFilter`

```php
protected function registerEntityFilter($entityClassName, $filterName, $filterClassName, $filterMethod)
```

**`init()`.** Додає власний фільтр до чужої сутності — щоб `find(['мій_фільтр' => …])`
працював. Клас фільтра **мусить** успадковувати `Okay\Core\Modules\AbstractModuleEntityFilter`,
інакше реєстрація кидає виняток. Базовий клас віддає `$this->select`, у який фільтр і дописує
умови.

### `setDefaultOrderFields`

```php
protected function setDefaultOrderFields($entityClassName, $newOrderFields, $redefine)
```

**`init()`.** Додає поля до сортування за замовчуванням або (при `$redefine = true`) замінює
його цілком.

### `registerEntityLangInfo`

```php
protected function registerEntityLangInfo($entityClassName, $langTable, $langObject)
```

**`init()`.** Робить немультимовну сутність мультимовною, вказавши їй мовну таблицю й об'єкт.

## Розширення чужої логіки

### `registerChainExtension`

```php
protected function registerChainExtension($expandable, $extension)
```

**`init()`.** Перехоплює результат методу, змінює його й повертає далі. Докладно —
[extenders.md](extenders.md).

### `registerQueueExtension`

```php
protected function registerQueueExtension($expandable, $extension)
```

**`init()`.** Виконує побічну дію після методу; повернене значення відкидається.

Обидва приймають або `['class' => X::class, 'method' => 'y']`, або короткий список
`[X::class, 'y']` — модулі репозиторію користуються другим.

## Адмінка

### `setBackendMainController`

```php
protected function setBackendMainController($className)
```

**`install()`.** Контролер, на який відкривається модуль зі списку модулів. Записується
**коротке ім'я класу** (`'FAQsAdmin'`), не `Vendor.Module.Controller`.

### `registerBackendController`

```php
protected function registerBackendController($controllerClass)
```

**`init()`.** Робить контролер адмінки доступним. Контролер має лежати в
`Backend/Controllers/`, успадковувати `Okay\Admin\Controllers\IndexAdmin` і мати метод
`fetch()` — інакше виняток. Якщо каталогу `Backend/Controllers/` немає взагалі, метод тихо
нічого не робить.

### `addPermission`

```php
protected function addPermission($permission)
```

**`init()`.** Додає дозвіл у список дозволів менеджерів. Потрібен, коли дозвіл є, а
контролера під нього немає. Повторний виклик із тим самим іменем нічого не робить.

### `addBackendControllerPermission`

```php
protected function addBackendControllerPermission($controllerClass, $permission)
```

**`init()`.** Зв'язує контролер із дозволом. Викликати `addPermission()` окремо **не треба** —
цей метод робить це сам. Повторна прив'язка того самого контролера кидає
`Permission for controller "…" already exists`.

### `extendBackendMenu`

```php
protected function extendBackendMenu($firstLevelName, array $menuItemsByControllers, $icon = null)
```

**`init()`.** Додає пункти в ліве меню адмінки.

```php
$this->extendBackendMenu('left_faq_title', [
    'left_faq_title' => ['FAQsAdmin', 'FAQAdmin'],
]);
```

- `$firstLevelName` — ключ перекладу кореневого пункту. Наявний ключ означає, що пункти
  допишуться в кінець наявної групи.
- Ключі масиву — ключі перекладу пунктів, значення — короткі імена контролерів (метод сам
  перетворює їх на `Vendor.Module.Controller`).
- `$icon` — шлях до файлу відносно каталогу модуля **або** сам текст SVG.

Дубль пари «група → пункт» кидає `Menu item by path … already in use`.

Щоб побачити наявні імена груп, увімкніть `dev_mode` — [../configuration.md](../configuration.md#dev_mode).

### `addFastMenuItem`

```php
protected function addFastMenuItem($dataProperty, ...$menuItems)
```

**`init()`.** Меню швидкого редагування, що спливає при наведенні на елемент з атрибутом
`data-<dataProperty>`. Кожен пункт — масив із обов'язковими `controller` і `translation` та
необов'язковими `params` і `action` (`add` або `edit`).

### `extendUpdateObject`

```php
protected function extendUpdateObject($alias, $permission, $entityClassName)
```

**`init()`.** Дозволяє оновлювати сутність AJAX-запитом з адмінки (наприклад, перемикачем
видимості в списку). Псевдонім вказується в розмітці як `data-controller="псевдонім"`.
Прийнята в репозиторії форма — `'OkayCMS.FAQ.FAQEntity'`. Дубль псевдоніма кидає виняток.

### `setSystem`

```php
protected function setSystem()
```

**`install()`.** Позначає модуль системним — він зникає зі списку модулів для менеджерів без
відповідних прав.

## Блоки дизайну

### `addBackendBlock`

```php
protected function addBackendBlock($blockName, $blockTplFile, $callback = null)
```

**`init()`.** Вставляє шаблон у named-блок адмінки. Файл шукається в
`Backend/design/html/` модуля; шлях зводиться до імені файлу. Неіснуючий файл кидає виняток.

### `addFrontBlock`

```php
protected function addFrontBlock($blockName, $blockTplFile, $callback = null)
```

**`init()`.** Те саме для вітрини, але з підміною темою: спершу шукається
`design/<тема>/modules/<Vendor>/<Module>/html/<файл>`, і лише потім `design/html/` модуля.

`$callback` виконується перед відмальовуванням блоку; його аргументи резолвляться через DI за
тайп-хінтом:

```php
$this->addBackendBlock(
    'notification_counters',
    'counter_block.tpl',
    function (Design $design, CurrenciesEntity $currenciesEntity) {
        if (!$currenciesEntity->findOne(['code' => 'UAH'])) {
            $design->assign('uahCurrencyError', true);
        }
    }
);
```

Імена доступних блоків показує `dev_mode`. Докладно — [backend.md](backend.md#блоки-дизайну)
і [frontend.md](frontend.md#блоки-дизайну).

## Інше

### `setModuleType`

```php
protected function setModuleType($type)
```

**`install()`.** Приймає лише константи `MODULE_TYPE_PAYMENT`, `MODULE_TYPE_DELIVERY`,
`MODULE_TYPE_XML`; будь-що інше кидає `Type "…" not supported`. Тип впливає на те, де модуль
з'явиться в адмінці, і на те, чи читатиметься його `settings.xml`.

### `addResizeObject`

```php
protected function addResizeObject($originalImgDirDirective, $resizedImgDirDirective)
```

**`init()`.** Реєструє пару каталогів для нарізки зображень. Аргументи — **імена директив
конфіга**, а не шляхи.

### `registerSchedule`

```php
public function registerSchedule(Schedule $schedule): void
```

**`init()`.** Періодична задача.

```php
$this->registerSchedule(
    (new Schedule([NPCacheHelper::class, 'cronUpdateCitiesCache']))
        ->name('Parses NP cities to the db cache')
        ->time('0 0 * * *')
        ->overlap(false)
        ->timeout(3600)
);
```

Задачі існують лише після підняття модулів, тож `./ok scheduler:list` спершу піднімає всі
увімкнені модулі. Докладно — [../cli.md](../cli.md).

### Знижки

```php
public function registerPurchaseDiscountSign(string $sign, string $name, string $description)
public function registerCartDiscountSign(string $sign, string $name, string $description)
```

**`init()`.** Реєструють ознаку знижки на товар у замовленні й на все замовлення відповідно —
[../discounts.md](../discounts.md).

---

## Чого в `AbstractInit` немає

Назви, яких часто шукають і які не існують:

| Шукають | Насправді |
| ------- | --------- |
| `addBackendController()` | `registerBackendController()` |
| `addFrontController()` | front-контролер реєструється маршрутом у `Init/routes.php` — [frontend.md](frontend.md#сторінка-вітрини) |
| `setTemplateDir()` | `Design::setModuleDir()` / `Design::rollbackTemplatesDir()` |

## Що не використовує жоден модуль репозиторію

`setSystem()`, `registerEntityAdditionalField()`, `setDefaultOrderFields()`,
`registerEntityLangInfo()`, `registerPurchaseDiscountSign()`, `registerCartDiscountSign()`.
Вони робочі, але живого зразка для них у репозиторії немає — перевіряйте поведінку на місці.
