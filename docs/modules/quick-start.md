# Модуль: швидкий старт

Проходимо шлях від порожнього каталогу до встановленого модуля з розділом в адмінці.
Далі — [structure.md](structure.md) про структуру й [init-reference.md](init-reference.md)
про доступні методи.

## 1. Каркас

```bash
cd dev && docker compose exec php85 php ok module:create
```

Команда інтерактивна: питає вендора (або пропонує створити нового) та ім'я модуля, потім
розкладає каркас у `Okay/Modules/<Vendor>/<Module>/`:

```
Init/Init.php          клас Init із install() та init()
Init/routes.php        маршрути модуля
Init/services.php      сервіси модуля
Init/parameters.php    параметри модуля
Backend/Controllers/ModuleAdmin.php
Backend/design/html/module.tpl
Backend/lang/{en,ru,ua}.php
design/js.php
design/css.php
```

**Каркас не створює `Init/module.json`.** Без нього модуль отримає версію `1.0.0` за
замовчуванням, і жоден `update_x_y_z()` не спрацює як слід. Створіть файл одразу —
[structure.md](structure.md#modulejson).

Згенерований `Init.php` уже робочий:

```php
class Init extends AbstractInit
{
    const PERMISSION = 'vendor__module';

    public function install()
    {
        $this->setBackendMainController('ModuleAdmin');
    }

    public function init()
    {
        $this->addPermission(self::PERMISSION);
        $this->registerBackendController('ModuleAdmin');
        $this->addBackendControllerPermission('ModuleAdmin', self::PERMISSION);
    }
}
```

## 2. `module.json`

```json
{
  "version": "1.0.0",
  "vendor": {
    "email": "you@example.com",
    "site": "https://example.com"
  }
}
```

Версія — **рівно три числа через крапку**. Інший формат перетворюється на «версію 0», і
механізм оновлень перестає працювати мовчки.

## 3. Встановлення

Адмінка → «Модулі» → навпроти модуля «Встановити». У цей момент:

1. у `ok_modules` з'являється рядок із `enabled = 1` і версією з `module.json`;
2. викликається `Init::install()`;
3. викликаються всі `update_x_y_z()` від `1.0.0` до версії з маніфеста — **так, і на чистій
   інсталяції теж** ([lifecycle.md](lifecycle.md#оновлення)).

Кнопка встановлення — єдина точка входу: `install()` із консолі не викликається.

## 4. Своя сутність і таблиця

Клас сутності — у `Entities/`, таблиця створюється в `install()`:

```php
// Okay/Modules/OkayCMS/FAQ/Init/Init.php
public function install()
{
    $this->setBackendMainController('FAQsAdmin');
    $this->migrateEntityTable(FAQEntity::class, [
        (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
        (new EntityField('question'))->setTypeText()->setIsLang(),
        (new EntityField('answer'))->setTypeText()->setIsLang()->setNullable(),
        (new EntityField('visible'))->setTypeTinyInt(1),
        (new EntityField('position'))->setTypeInt(11),
    ]);
}
```

Ім'я таблиці береться із самої сутності, не з аргументів. Реєструвати клас сутності ніде не
треба — його віддає `EntityFactory` за неймспейсом. Деталі — [migrations.md](migrations.md).

## 5. Розділ в адмінці

```php
public function init()
{
    $this->registerBackendController('FAQsAdmin');
    $this->addBackendControllerPermission('FAQsAdmin', 'okaycms__faq__faq');

    $this->extendBackendMenu('left_faq_title', [
        'left_faq_title' => ['FAQsAdmin', 'FAQAdmin'],
    ]);
}
```

Контролер має лежати в `Backend/Controllers/`, успадковувати `Okay\Admin\Controllers\IndexAdmin`
і мати метод `fetch()` — інакше реєстрація кине виняток. URL контролера:
`?controller=Vendor.Module.FAQsAdmin`. Докладно — [backend.md](backend.md).

## 6. Сторінка вітрини

Front-контролер реєструється **не методом, а маршрутом** — `Init/routes.php`:

```php
// Okay/Modules/OkayCMS/FAQ/Init/routes.php
namespace Okay\Modules\OkayCMS\FAQ;

return [
    'OkayCMS_FAQ_main' => [
        'slug' => '/faq',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FAQController',
            'method' => 'render',
        ],
    ],
];
```

Докладно — [frontend.md](frontend.md).

## 7. Перевірка

- Модуль не з'явився в списку — перевірте, що каталог лежить у
  `Okay/Modules/<Vendor>/<Module>/` і що `Init/Init.php` оголошує клас `Init`.
- «Controller … not exists» під час установки — `registerBackendController()` отримав ім'я,
  якому не відповідає файл у `Backend/Controllers/`.
- Змінили `module.json` → шаблони не оновились: почистіть `compiled/`.
- Змінили `Init/services.php` → «service class does not exist»: перевірте, що клас справді
  існує й неймспейс збігається з каталогом.
