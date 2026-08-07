<?php


namespace Okay\Entities;


use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;

class RouterCacheEntity extends Entity
{

    const TYPE_PRODUCT = 'product';
    const TYPE_CATEGORY = 'category';
    const TYPE_POST = 'post';
    const TYPE_BLOG_CATEGORY = 'blog_category';
    
    protected static $fields = [
        'url',
        'slug_url',
        'type',
    ];

    protected static $table = 'router_cache';

    /**
     * Свій add() замість CRUD::add() з двох причин.
     *
     * Перша: у таблиці немає колонки `id`, тож базовий метод однаково доходить
     * до `if (!$id = $this->db->insertId())` і завжди повертає false, не
     * виконуючи блок мовних полів. Тут його просто нема сенсу проходити.
     *
     * Друга, головна: звичайний INSERT давав 1062 Duplicate entry щоразу, коли
     * урл відрізнявся від закешованого лише регістром — унікальний індекс
     * url_type живе в utf8mb4_general_ci. Нижній регістр для `url` плюс
     * ON DUPLICATE KEY UPDATE роблять запис ідемпотентним і знімають гонку
     * двох одночасних вставок того самого урла.
     *
     * Наявні рядки у змішаному регістрі лагодяться самі, у парі з
     * AbstractRoute::mergeUrlSlugAlias(): той не підхоплює рядок, url якого не
     * в нижньому регістрі, тож пошук у кеші промахується, стратегія генерує
     * slug заново з джерела і доходить сюди — а ON DUPLICATE KEY UPDATE
     * переписує рядок уже правильним. Ручний DELETE для цього не потрібен.
     *
     * `slug_url` навмисно лишається як є: він складається з
     * `$category->url . '/' . $product->url`, тобто вже несе регістр джерела.
     *
     * @param array|object $object
     * @return bool
     */
    public function add($object)
    {
        // Пишемо в окрему змінну, а не в $object: func_get_args() віддає
        // поточні значення параметрів, і екстендери мають бачити те, що
        // передав викликач, а не наш відфільтрований масив.
        // Тільки колонки, які є в таблиці: `id` тут не існує, а решту ключів
        // до cols() пускати нема за чим.
        $cols = array_intersect_key((array) $object, array_flip(self::getFields()));

        // Той самий вираз, що й AbstractRoute::normalizeAliasKey(): обидві
        // половини правки мусять згортати регістр однаково, інакше запис і
        // пошук знову розійдуться.
        $cols['url']      = mb_strtolower((string) ($cols['url'] ?? ''), 'UTF-8');
        $cols['slug_url'] = (string) ($cols['slug_url'] ?? '');
        $cols['type']     = (string) ($cols['type'] ?? '');

        // Неповний рядок кешувати нема сенсу: getUrlSlugAlias() перевіряє
        // через !empty(), тож порожній slug однаково вважається відсутнім
        // кешем. Порожній slug виникає, коли сутність не знайшлась
        // ($product->url на false дає null). З ON DUPLICATE KEY UPDATE такий
        // запис став би ще й руйнівним: там, де INSERT просто падав і лишав
        // чинний рядок недоторканим, upsert затер би його порожнім значенням.
        if ($cols['url'] === '' || $cols['slug_url'] === '' || $cols['type'] === '') {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }

        $insert = $this->queryFactory->newInsert();
        $insert->into(self::getTable())
            ->cols($cols)
            // Саме пари ключ-значення: onDuplicateKeyUpdateCol() без значення
            // створює плейсхолдер і нічого в нього не біндить.
            ->onDuplicateKeyUpdateCols([
                'url'      => $cols['url'],
                'slug_url' => $cols['slug_url'],
            ]);

        // Database::query() ловить виняток і повертає false — віддаємо саме
        // його, а не беззастережне true, щоб виклик міг відрізнити записаний
        // рядок від відхиленого.
        $result = (bool) $this->db->query($insert);

        return ExtenderFacade::execute([static::class, __FUNCTION__], $result, func_get_args());
    }

    public function deleteByUrl($objectType, $url)
    {
        $delete = $this->queryFactory->newDelete();
        
        $delete->from(self::getTable())
            ->where('type=:type')
            ->where('url in (:url)')
            ->bindValue('type', $objectType)
            ->bindValue('url', (array)$url)
            ->execute();
        
        return true;
    }
    
    public function deleteProductsCache()
    {
        $delete = $this->queryFactory->newDelete();
        
        $delete->from(self::getTable())
            ->where('type="product"')
            ->execute();
        
        return true;
    }
    
    public function deleteCategoriesCache()
    {
        $delete = $this->queryFactory->newDelete();
        
        $delete->from(self::getTable())
            ->where('type="category"')
            ->execute();
        
        return true;
    }
    
    public function deleteBlogCategoriesCache()
    {
        $delete = $this->queryFactory->newDelete();
        
        $delete->from(self::getTable())
            ->where('type="blog_category"')
            ->execute();
        
        return true;
    }
    
    public function deleteBlogCache()
    {
        $delete = $this->queryFactory->newDelete();
        
        $delete->from(self::getTable())
            ->where('type="post"')
            ->execute();
        
        return true;
    }

    /**
     * Метод удаляет неактуальный кеш, нужно вызывать при удалении или обновлении категорий или товаров
     * 
     * @return bool
     */
    public function deleteWrongCache()
    {
        // Удаляем ненужный кеш товаров
        $sql = $this->queryFactory->newSqlQuery();
        $sql->setStatement("DELETE r FROM " . self::getTable() . " AS r 
            LEFT JOIN " . ProductsEntity::getTable() . " AS p ON p.url=r.url AND r.type='product'
            WHERE r.type='product' AND p.id IS NULL")
            ->execute();

        // Удаляем ненужный кеш категорий
        $sql = $this->queryFactory->newSqlQuery();
        $sql->setStatement("DELETE r FROM " . self::getTable() . " AS r 
            LEFT JOIN " . CategoriesEntity::getTable() . " AS c ON c.url=r.url AND r.type='category'
            WHERE r.type='category' AND c.id IS NULL")
            ->execute();

        // Удаляем ненужный кеш категорий блога
        $sql = $this->queryFactory->newSqlQuery();
        $sql->setStatement("DELETE r FROM " . self::getTable() . " AS r 
            LEFT JOIN " . BlogCategoriesEntity::getTable() . " AS c ON c.url=r.url AND r.type='blog_category'
            WHERE r.type='blog_category' AND c.id IS NULL")
            ->execute();

        // Удаляем ненужный кеш блога
        $sql = $this->queryFactory->newSqlQuery();
        $sql->setStatement("DELETE r FROM " . self::getTable() . " AS r 
            LEFT JOIN " . BlogEntity::getTable() . " AS c ON c.url=r.url AND r.type='post'
            WHERE r.type='post' AND c.id IS NULL")
            ->execute();
        
        return true;
    }
    
    public function getCategoriesUrlsWithoutCache()
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['c.url'])
            ->from(CategoriesEntity::getTable() . ' AS c')
            ->leftJoin(self::getTable() . ' AS r', 'c.url=r.url AND r.type = "category"')
            ->where('r.url IS NULL');
        
        return $select->results('url');
    }
    
    public function getProductsUrlsWithoutCache()
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['p.url'])
            ->from(ProductsEntity::getTable() . ' AS p')
            ->leftJoin(self::getTable() . ' AS r', 'p.url=r.url AND r.type = "product"')
            ->where('r.url IS NULL');
        
        return $select->results('url');
    }

    public function getBlogCategoriesUrlsWithoutCache()
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['c.url'])
            ->from(BlogCategoriesEntity::getTable() . ' AS c')
            ->leftJoin(self::getTable() . ' AS r', 'c.url=r.url AND r.type = "blog_category"')
            ->where('r.url IS NULL');

        return $select->results('url');
    }

    public function getBlogUrlsWithoutCache()
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['c.url'])
            ->from(BlogEntity::getTable() . ' AS c')
            ->leftJoin(self::getTable() . ' AS r', 'c.url=r.url AND r.type = "blog"')
            ->where('r.url IS NULL');

        return $select->results('url');
    }
    
}
