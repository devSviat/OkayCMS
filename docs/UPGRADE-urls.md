# Оновлення: url сутностей зводяться в нижній регістр

Стосується магазинів, які оновлюються на версію з нормалізацією url в адмінці.
Дія потрібна **до** викатки, і лише якщо перевірка нижче щось знайде.

## Що змінилось

Сім реквестів адмінки — товари, категорії, статті, категорії блога, бренди,
сторінки, автори — зводять `url` у нижній регістр при **будь-якому** збереженні,
не лише на створенні. Автогенерація url із назви й CSV-імпорт робили це й
раніше, через `Translit::translit()`.

Причина: MySQL порівнює url у `_ci`, тобто `Item-1` і `item-1` для бази це один
рядок, а для PHP — два. На цьому розходженні `ok_router_cache` набирав
`1062 Duplicate entry`, а перевірки унікальності в адмінці бачили різні
значення там, де база бачить однакові.

## Перевірка перед оновленням

```sql
SELECT 'products',        COUNT(*) FROM ok_products        WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'categories',      COUNT(*) FROM ok_categories      WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'pages',           COUNT(*) FROM ok_pages           WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'brands',          COUNT(*) FROM ok_brands          WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'blog',            COUNT(*) FROM ok_blog            WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'blog_categories', COUNT(*) FROM ok_blog_categories WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'authors',         COUNT(*) FROM ok_authors         WHERE BINARY url <> BINARY LOWER(url)
UNION ALL SELECT 'router_cache',    COUNT(*) FROM ok_router_cache    WHERE BINARY url <> BINARY LOWER(url);
```

**Усюди нулі — робити нічого не треба.** Оновлюйтесь і читайте далі лише з
цікавості: жоден зі сценаріїв нижче спрацювати не може.

## Якщо десь не нуль

Такі рядки треба вирівняти **разово, до викатки**. Інакше буде два наслідки, і
обидва тихі.

**Адреса зміниться сама.** Поле `url` у картці наявної сутності `readonly`, але
readonly-поле браузер усе одно надсилає. Тобто перше ж збереження картки — хай
навіть заради одного мета-тега — перепише url у нижній регістр. Для товарів,
сторінок і брендів стара адреса далі працює: їх шукають SQL-запитом, а він у
`_ci`. **Для категорій і категорій блога — ні**: `CategoriesEntity::get()` і
`BlogCategoriesEntity::get()` порівнюють url у PHP, чутливо до регістру, тож
`/catalog/Katalog` після такого збереження віддасть 404. Редиректа ніхто не
створює.

Заразом `CategoryAdmin` побачить зміну url і викличе `deleteProductsCache()` —
кеш маршрутів товарів злетить увесь і відбудовуватиметься наново.

**Перевірка на дублі промахнеться.** Якщо в базі лежить категорія `Katalog`, а
менеджер створює нову й вписує той самий `Katalog`, реквест зведе введене в
`katalog`, а `get('katalog')` наявний рядок уже не знайде — і в базі опиняться
дві категорії, які MySQL у `_ci` вважає однією. UNIQUE на таблиці немає, тож
ніщо це не спинить.

Обидва сценарії живуть рівно доти, доки в базі є хоч один url зі змішаним
регістром. Після вирівнювання вони недосяжні за побудовою.

## Порядок вирівнювання

1. Зробити дамп.
2. Перевірити, чи згортання не склеює дві різні сутності в одну:

   ```sql
   SELECT LOWER(url), COUNT(*) c FROM ok_categories GROUP BY LOWER(url) HAVING c > 1;
   ```

   Те саме для решти таблиць. Якщо щось знайшлось — спершу розвести адреси
   вручну, бо `UPDATE` нижче зробить із них справжні дублікати.
3. Звести регістр:

   ```sql
   UPDATE ok_products        SET url = LOWER(url) WHERE BINARY url <> BINARY LOWER(url);
   -- і так далі по кожній таблиці зі списку, включно з ok_router_cache
   ```
4. Якщо змінені адреси вже проіндексовані — поставити на них 301 зі старих.
5. Викатувати код.
