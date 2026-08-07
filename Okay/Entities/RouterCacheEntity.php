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
     * Наявні рядки у змішаному регістрі це саме по собі не лагодить: після
     * нормалізації ключа в AbstractRoute пошук у кеші починає в них влучати,
     * тож до цього методу виконання просто не доходить. Такі рядки чистяться
     * разовим DELETE ... WHERE BINARY url <> BINARY LOWER(url), після чого
     * перегенеровуються тут уже правильними.
     *
     * `slug_url` навмисно лишається як є: він складається з
     * `$category->url . '/' . $product->url`, тобто вже несе регістр джерела.
     *
     * @param array|object $object
     * @return bool
     */
    public function add($object)
    {
        // Тільки колонки, які є в таблиці: `id` тут не існує, а решту ключів
        // до cols() пускати нема за чим.
        $object = array_intersect_key((array) $object, array_flip(self::getFields()));
        $object['url'] = mb_strtolower((string) ($object['url'] ?? ''), 'UTF-8');

        $insert = $this->queryFactory->newInsert();
        $insert->into(self::getTable())
            ->cols($object)
            // Саме пари ключ-значення: onDuplicateKeyUpdateCol() без значення
            // створює плейсхолдер і нічого в нього не біндить.
            ->onDuplicateKeyUpdateCols([
                'url'      => $object['url'],
                'slug_url' => $object['slug_url'] ?? '',
            ]);

        $this->db->query($insert);

        return ExtenderFacade::execute([static::class, __FUNCTION__], true, func_get_args());
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
