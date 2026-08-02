# Таблиці й поля

Схему модуль змінює **тільки** через методи `AbstractInit` — сирий SQL для DDL не пишемо.
Усі вони викликаються з `install()` або з `update_x_y_z()`, ніколи з `init()`.

## Своя таблиця

```php
use Okay\Core\Modules\EntityField;

public function install()
{
    $this->migrateEntityTable(FAQEntity::class, [
        (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
        (new EntityField('question'))->setTypeText()->setIsLang(),
        (new EntityField('answer'))->setTypeText()->setIsLang()->setNullable(),
        (new EntityField('visible'))->setTypeTinyInt(1),
        (new EntityField('position'))->setTypeInt(11),
    ]);
}
```

**Ім'я таблиці береться з класу сутності**, а не з аргументів. Сутність оголошує його сама:

```php
// Okay/Modules/OkayCMS/FAQ/Entities/FAQEntity.php
class FAQEntity extends Entity
{
    protected static $table     = '__okaycms__faq__faq';
    protected static $langTable = 'okaycms__faq__faq';
    protected static $langObject = 'faq';
    …
}
```

Провідні `__` — це заповнювач префікса таблиць: перед виконанням запиту він замінюється на
`db_prefix` із конфіга. Заповнювач нормалізується, тож `'__okaycms__faq__faq'` і
`'okaycms__faq__faq'` в оголошенні сутності дають ту саму таблицю. Мовна отримає ім'я
`__lang_okaycms__faq__faq` — у базі з типовим префіксом це `ok_okaycms__faq__faq` і
`ok_lang_okaycms__faq__faq`.

Якщо сутність оголошує мовну таблицю (`$langTable` + `$langObject`), `migrateEntityTable()`
створює **дві** таблиці: основну з усіма полями і `__lang_*` з полями, позначеними
`setIsLang()`. У мовну автоматично додаються `lang_id`, `<langObject>_id` і унікальний ключ по
цій парі. Порожній `$langObject` при заповненому `$langTable` кидає виняток.

**Метод не ідемпотентний.** Він виконує голий `CREATE TABLE` без `IF NOT EXISTS`, тож повторний
виклик на наявній таблиці впаде на рівні бази. Це важливо, бо `update_x_y_z()` виконуються і
на чистій інсталяції ([lifecycle.md](lifecycle.md#встановлення)).

Таблиці створюються як `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.

### Таблиця зв'язків

```php
$this->migrateCustomTable('okaycms__module_products', [
    (new EntityField('module_id'))->setTypeInt(11, false),
    (new EntityField('product_id'))->setTypeInt(11, false),
]);
```

Префікс `__` додається автоматично й не дублюється: `'okaycms__x'` і `'__okaycms__x'` дадуть
один результат.

## Поле в чужій таблиці

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

Два кроки не взаємозамінні: перший створює колонку, другий повідомляє ORM, що вона є. Без
другого поле не потрапить ані у вибірку, ані у фільтри.

`migrateEntityField()` дивиться на наявні колонки й вирішує сам:

- колонки немає → `ADD COLUMN`;
- колонка є, але відрізняється типом, обнулюваністю чи `DEFAULT` → `CHANGE`;
- усе збігається → нічого.

Для поля з `setIsLang()` колонка додається **і в основну, і в мовну** таблицю сутності.

## `EntityField`

Усі сеттери повертають `$this`, тож викликаються ланцюжком. Ім'я поля в конструкторі
очищується від усього, крім літер, цифр і підкреслення.

### Типи

```php
setTypeVarchar($length, $nullable = true)      // $length мусить бути int, інакше виняток
setTypeInt($length, $nullable = true)          // те саме
setTypeTinyInt($length, $nullable = true)      // те саме
setTypeFloat($length, $nullable = true)
setTypeDecimal($length, $nullable = true)      // приймає рядок: setTypeDecimal('10,5')
setTypeText()
setTypeMediumText()
setTypeLongText()
setTypeEnum(array $values, $nullable = true)
setTypeDatetime($nullable = true)
setTypeTimestamp($nullable = true, $default = 'current_timestamp()')
```

### Модифікатори

```php
setDefault($default)
setNullable()        unsetNullable()
setIsLang()          unsetIsLang()
setAutoIncrement()   unsetAutoIncrement()      // setAutoIncrement() сам робить поле первинним ключем
```

### Індекси

```php
setIndexPrimaryKey()                                unsetPrimaryKey()
setIndex($length = null, EntityField ...$fields)    // складений індекс: додаткові поля через аргументи
setIndexUnique($length = null, EntityField ...$fields)   unsetIndexUnique()
setIndexFulltext()
```

`$length` — префіксна довжина індексу; для довгих `varchar` вона потрібна:

```php
(new EntityField('name'))->setTypeVarchar(255, true)->setIsLang()->setIndex(100),
```

Більше ніж один первинний ключ у таблиці кидає `Table can use only one primary key`.

## Пастки

**Порядок виклику має значення.** Кожен сеттер типу першим ділом скидає тип, довжину,
значення за замовчуванням і обнулюваність. Тобто:

```php
(new EntityField('x'))->setDefault('0')->setTypeInt(11);   // ✗ default мовчки зникає
(new EntityField('x'))->setTypeInt(11)->setDefault('0');   // ✓
```

**Унікальні індекси на мовних полях знімаються.** Будуючи мовну таблицю,
`migrateEntityTable()` викликає `unsetIndexUnique()` на кожному полі з `setIsLang()` — і робить
це **на переданих вами об'єктах**. Якщо ви тримаєте той самий об'єкт `EntityField` у змінній і
використовуєте його далі, унікальності на ньому вже не буде.

**`DEFAULT` із дужками не береться в лапки.** Значення, схоже на виклик функції
(`current_timestamp()`), потрапляє в SQL без лапок — саме тому працює `setTypeTimestamp()`. Але
рядкове значення, що містить дужки, теж піде без лапок і зламає запит.

**Первинний ключ завжди `NOT NULL`**, незалежно від `$nullable`.

## Вбудовані модулі

Схема модулів, що постачаються з системою, живе ще й у `1DB_changes/okay_clean.sql`. Зміна
схеми такого модуля має потрапити **і в дамп, і в `update_x_y_z()`** — інакше вона застосується
лише на нових інсталяціях або лише на наявних. `./ok database:deploy` просто заливає дамп і з
механізмом модулів не пов'язаний.
