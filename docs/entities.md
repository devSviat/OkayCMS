# Сутності (Entity)

Сутність — шар доступу до однієї таблиці. Базовий клас — `Okay\Core\Entity\Entity`, конкретні
сутності живуть в `Okay/Entities/` (і в `Entities/` кожного модуля).

**Сирий SQL для `get`, `find`, `add`, `update`, `delete` не пишеться.** Базовий клас робить це
сам; коли потрібна складніша вибірка, беруть об'єкт `Select` і дописують до нього умови.

## Оголошення сутності

```php
// Okay/Entities/BrandsEntity.php
class BrandsEntity extends Entity
{
    protected static $fields = ['id', 'url', 'image', 'last_modify', 'visible', 'position'];

    protected static $langFields = [
        'name', 'name_h1', 'meta_title', 'meta_keywords',
        'meta_description', 'annotation', 'description',
    ];

    protected static $searchFields = ['name', 'meta_keywords'];
    protected static $defaultOrderFields = ['position'];

    protected static $table = '__brands';
    protected static $langObject = 'brand';
    protected static $langTable = 'brands';
    protected static $tableAlias = 'b';
    protected static $alternativeIdField = 'url';
}
```

| Властивість | Що означає |
| ----------- | ---------- |
| `$fields` | колонки основної таблиці |
| `$langFields` | колонки мовної таблиці `__lang_*` |
| `$additionalFields` | поля, до яких **не** додається префікс таблиці: підзапити, обчислені колонки |
| `$searchFields` | колонки, якими працює фільтр `keyword` |
| `$table` | ім'я таблиці; провідні `__` — заповнювач префікса з конфіга (`db_prefix`) |
| `$langObject` | основа зовнішнього ключа мовної таблиці: `brand` → `brand_id` |
| `$langTable` | ім'я мовної таблиці **без** `__lang_` |
| `$tableAlias` | псевдонім таблиці в запиті; без нього береться перша літера імені таблиці |
| `$defaultOrderFields` | сортування за замовчуванням |
| `$alternativeIdField` | дозволяє `get('sony')` замість `get(12)` |

Імена нормалізуються, тож `'brands'` і `'__brands'` в оголошенні `$table` рівнозначні.

**Властивість зветься `$tableAlias`, не `$alias`.** Властивості `$defaultActiveField` і класів
`EntityRepository`, `EntityFieldsHelper`, які трапляються в старих текстах, у цьому коді немає.

## Отримання сутності

Сутності **не є сервісами DI**. Їх видає `EntityFactory`:

```php
public function fetch(EntityFactory $entityFactory)
{
    /** @var BrandsEntity $brandsEntity */
    $brandsEntity = $entityFactory->get(BrandsEntity::class);
}
```

У контролери й хелпери фабрика приходить через тайп-хінт. Там, де DI недоступний (наприклад,
в `update_x_y_z()` модуля), — через [ServiceLocator](di.md#service-locator).

Фабрика кешує об'єкти: два виклики `get()` повернуть той самий екземпляр.

## Читання

```php
public function get($id);                       // за id або за $alternativeIdField
public function findOne(array $filter = []);    // перший рядок або false
public function find(array $filter = []);       // масив
public function count(array $filter = []);      // COUNT(DISTINCT alias.id)
public function getSelect(array $filter = []);  // об'єкт Select для доопрацювання
```

```php
$brand  = $brandsEntity->get(5);
$brand  = $brandsEntity->get('sony');                    // через $alternativeIdField
$brands = $brandsEntity->find(['visible' => 1, 'limit' => 20]);
$total  = $brandsEntity->count(['visible' => 1]);
```

`find()` із однією запитаною колонкою повертає **плоский масив значень**, а не масив об'єктів:

```php
$urls = $brandsEntity->cols(['url'])->find();   // ['sony', 'lg', …]
```

### Обмеження вибірки

```php
public function cols(array $cols);      // список колонок
final public function col($colName);    // одна колонка; приймає лише оголошене поле
public function noLimit();              // зняти пагінацію
public function mappedBy($columnName);  // ключі результату — значення цієї колонки
```

`mappedBy()` приймає лише колонку зі списку полів сутності, інакше кидає
`Incorrect column name in mappedBy`.

### Пастка: «ліміт 100 за замовчуванням»

Успадкована неточність. Насправді ліміт залежить від того, що є у фільтрі:

| Фільтр | SQL |
| ------ | --- |
| `find([])` | **без `LIMIT` узагалі** — повний перебір таблиці |
| `find(['limit' => 5])` | `LIMIT 5` |
| `find(['page' => 2])` | `LIMIT 100 OFFSET 100` |
| `find(['limit' => 5, 'page' => 3])` | `LIMIT 5 OFFSET 10` |

Причина: aura/sqlquery не друкує `LIMIT`, доки не задано сторінку, а `limit` у фільтрі задає
сторінку примусово. Тобто 100 — це не запобіжник від великої вибірки, а розмір сторінки, що
спрацьовує лише разом із `page`.

Практичний висновок: якщо не хочете тягнути таблицю цілком, передавайте `limit` явно.
`noLimit()` потрібен рідко — він знімає пагінацію повністю і скидається після запиту.

## Запис

```php
public function add($object);           // повертає id або false
public function update($ids, $object);  // $ids — id або масив id
public function delete($ids);
```

```php
$id = $brandsEntity->add(['name' => 'Sony', 'url' => 'sony']);
$brandsEntity->update($id, ['visible' => 0]);
$brandsEntity->update([1, 2, 3], ['visible' => 0]);
$brandsEntity->delete($id);
```

Деталі, видні лише з коду:

- ключ `id` у переданому об'єкті ігнорується — і в `add()`, і в `update()`;
- значення `'now()'` (у будь-якому регістрі) підставляється як SQL-функція, а не як рядок;
- в `add()`, якщо сутність має поле `position` і його не передали, воно заповнюється id
  щойно доданого рядка;
- `update()` мовчки викидає значення-масиви й значення-об'єкти;
- `delete()` прибирає рядки і з мовної таблиці.

## Багатомовність

Мовні поля живуть у `__lang_<langTable>`, яка приєднується `LEFT JOIN`-ом автоматично за
поточною мовою. Псевдонім мовної таблиці — `l`.

### Головна мова — це найменша `position`

**Головна мова — це рядок `ok_languages` з найменшим значенням `position`.** Не «мова за
замовчуванням» і не окремий прапорець: такої колонки в таблиці немає взагалі. Головна мова не
отримує префікса в URL, а `/<її-label>/…` редіректиться на адресу без префікса.

### Базова таблиця дзеркалить головну мову

Найнеочевидніша поведінка ORM, якої в документації досі не було.

При **`update()`** мовні поля потрапляють у базову таблицю **лише коли поточна мова головна**.
Якщо запис редагують іншою мовою, мовні поля вирізаються з об'єкта до запису в базову таблицю
й ідуть тільки в `__lang_*`.

При **`add()`** вирізання не відбувається: у базову таблицю пишеться те, що прийшло, якою б
мовою запис не створювали.

Наслідки, з якими стикаються на практиці:

- у типовій інсталяції, де головна мова українська, а сидовий вміст російський, **перше
  збереження запису в адмінці замінює російський текст у базовій таблиці українським**. Це не
  втрата даних: російський текст лишається у своєму рядку `__lang_*` і на вітрині
  відображається;
- створення запису неголовною мовою кладе цю мову в базову таблицю — асиметрія з `update()`.

Базова таблиця й задумана як дзеркало головної мови: додаючи нову мову, система заповнює її
рядки в `__lang_*` копіюванням **із базової таблиці**.

### Куди пише що

| Операція | Базова таблиця | `__lang_*` |
| -------- | -------------- | ---------- |
| `add()` | подані значення як є | рядки для **всіх** мов |
| `update()` | лише якщо поточна мова головна | лише поточна мова |

## Фільтри

Фільтр — асоціативний масив, який приймають `find()`, `findOne()`, `count()` і `getSelect()`.
Кожен ключ обробляється в такому порядку:

1. фільтр, зареєстрований модулем для цієї сутності;
2. метод `filter__<ім'я>` самої сутності;
3. «магічний» фільтр.

### Магічні фільтри

Якщо ім'я ключа збігається з оголошеним полем (`$fields` або `$langFields`), умова додається
сама:

```php
$brandsEntity->find(['visible' => 1]);        // b.visible = :magic_filter_visible
$brandsEntity->find(['id' => [1, 2, 3]]);     // b.id IN (…)
$brandsEntity->find(['image' => null]);       // b.image IS NULL
```

Порожній масив як значення — не «нічого не знайдено», а **відсутність умови**: фільтр
пропускається. Ключ, якому не відповідає жодне поле, теж мовчки ігнорується — друкарська
помилка в імені фільтра не дасть ані помилки, ані порожньої вибірки.

Для мовного поля підставляється псевдонім мовної таблиці, не основної.

### Власний фільтр сутності

Оголошується методом `filter__<ім'я>`; усередині працюємо з `$this->select`:

```php
// Okay/Entities/BrandsEntity.php
protected function filter__selected_brands($brandsIds)
{
    $this->select->orWhere('b.id IN (:selected_brands)')
        ->bindValue('selected_brands', (array)$brandsIds);
}
```

Другим аргументом метод отримує **увесь масив фільтрів** — це дозволяє фільтру дивитись на
сусідні ключі:

```php
protected function filter__features($features, $filter)
```

Готовий фільтр `keyword` є в базовому класі й працює по `$searchFields`.

### Фільтр із модуля

Модуль додає фільтр до чужої сутності через `registerEntityFilter()`; клас фільтра успадковує
`Okay\Core\Modules\AbstractModuleEntityFilter` і отримує `$this->select` —
[modules/init-reference.md](modules/init-reference.md#registerentityfilter).

### Пріоритет фільтрів

```php
$entity->addHighPriority('visible')->find($filter);
$entity->addLowPriority('keyword')->find($filter);
```

Порядок умов впливає на те, які індекси обере MySQL. `addHighPriority()` піднімає фільтр
угору, `addLowPriority()` опускає, `resetPriority()` скидає налаштування.

## Сортування

```php
final public function order($order = null, array $additionalData = [])
```

Магічні варіанти: ім'я поля, `поле_asc`, `поле_desc` — за умови, що поле оголошене в
`$fields` або `$langFields`.

```php
$brandsEntity->order('name_desc')->find();
```

Псевдонім таблиці підставляється сам, для мовного поля — мовний. Без явного виклику діє
`$defaultOrderFields`. Складніші правила описуються методом `customOrder()` у самій сутності.

## `getSelect()` і складні запити

Коли фільтрів не вистачає, беруть готовий `Select` і дописують:

```php
$select = $productsEntity->getSelect(['visible' => 1]);
$select->join('LEFT', '__variants AS v', 'v.product_id = p.id')
       ->where('v.stock > 0');

$this->db->query($select);
$products = $this->db->results();
```

`getSelect()` — **єдиний метод читання без хука розширень**: у коді про це є прямий коментар.
Модулю треба розширювати `customChangeSelect()`.

### Пастка: імена плейсхолдерів у aura/sqlquery 3

Позиційних `?` у `where()` у трійці фактично немає — другий аргумент це масив іменованих
значень. Скалярний другий аргумент дає `TypeError` **під час виконання запиту**, а не при
завантаженні класу, тож ані phpstan, ані звичайні тести його не бачать. Від цього в
репозиторії стоїть окремий тест — `tests/Core/QueryFactory/NoPositionalBindsTest.php`.

Друга, підступніша частина: **масив, переданий другим аргументом `where()`, підставляється
текстово через `str_replace`**. `str_replace` не знає меж слова, тому ім'я одного
плейсхолдера не повинно бути префіксом іншого:

```php
// ✗ :sel_4_… влучить у початок :sel_43_… і зіпсує його
// ✓ номер усередині імені, суфікс сталий
$statements[] = "fv.feature_id = :sel_{$featureId}_id AND l.translit IN (:sel_{$featureId}_translits)";
$binds["sel_{$featureId}_id"] = $featureId;
$binds["sel_{$featureId}_translits"] = (array)$featureValuesTranslits;

$this->select->where('((' . implode(') OR (', $statements) . '))', $binds);
```

Це реальний обхід із `Okay/Entities/FeaturesValuesEntity.php` — саме на цьому тихо ламався
фільтр каталогу за характеристикою.

Масив, залишений на запиті через `bindValue()`, розкривається пізніше й іншим механізмом — там
повноцінний токенізатор, і цієї вади немає. Небезпечний саме `where($cond, $binds)` з масивом
у `$binds`.

## Відладка запитів

```php
$brands = $brandsEntity->debug()->find(['visible' => 1]);
```

Прапорець діє на один запит і скидається разом із рештою стану. Усі запити сторінки показує
панель відладки — [configuration.md](configuration.md#панель-відладки).

`Database::debug()` збирає читабельний варіант запиту з підставленими значеннями, але це
**імітація для показу**, а не той SQL, який виконав сервер; про це сказано в докблоці самого
методу.

## Розширення

CRUD-методи віддають результат через `ExtenderFacade`, тож модуль може вклинитись у будь-який
із них. У трейті використана форма `[static::class, __FUNCTION__]`, тому тригер — **конкретна
сутність** (`Okay\Entities\ProductsEntity::find`), а не трейт.

Без хука лишились `getSelect()` і `flush()` — [modules/extenders.md](modules/extenders.md).

## Сутність у модулі

Клас кладеться в `Entities/` модуля, таблиця створюється в `install()`; реєструвати сутність
ніде не треба — [modules/migrations.md](modules/migrations.md).
