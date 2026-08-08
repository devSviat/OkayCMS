<?php

namespace Core\Entity;

use Okay\Entities\CategoriesEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Категорії — єдина сутність, яку резолвлять обходом дерева в пам'яті, а не
 * SQL-запитом. Решта шукає в базі, де url живе в utf8mb4_general_ci, тож для
 * них `DELonghi` і `delonghi` — той самий рядок; доки тут стояло `==`,
 * категорії поводились інакше за все інше й за саму базу.
 *
 * Наслідків було два: перевірка на дубль промахувалась повз наявний рядок у
 * змішаному регістрі, а перейменування картки гасило стару адресу в 404, бо
 * контролер її більше не резолвив.
 */
class CategoriesEntityUrlLookupTest extends TestCase
{
    #[DataProvider('lookupProvider')]
    public function testUrlLookupIgnoresCase(string $storedUrl, string $lookedUpUrl): void
    {
        $entity = $this->entityWith([7 => $storedUrl]);

        $found = $entity->get($lookedUpUrl);

        $this->assertNotNull($found, "«{$lookedUpUrl}» мав знайти категорію «{$storedUrl}»");
        $this->assertSame(7, $found->id);
    }

    public static function lookupProvider(): array
    {
        return [
            'точний збіг'            => ['katalog', 'katalog'],
            'збережено у верхньому'  => ['Katalog', 'katalog'],
            'шукаємо у верхньому'    => ['katalog', 'Katalog'],
            'обидва змішані'         => ['KaTaLoG', 'kAtAlOg'],
            'кирилиця'               => ['Категорія-Тест', 'категорія-тест'],
            'кирилиця навпаки'       => ['категорія-тест', 'КАТЕГОРІЯ-Тест'],
        ];
    }

    /**
     * Нечутливість до регістру не має перетворитись на «знаходить будь-що».
     */
    public function testUnrelatedUrlStillReturnsNull(): void
    {
        $entity = $this->entityWith([7 => 'katalog']);

        $this->assertNull($entity->get('inshyi-katalog'));
    }

    /**
     * Пошук за id лишається як був — за точним ключем масиву.
     */
    public function testLookupByIdIsUnaffected(): void
    {
        $entity = $this->entityWith([7 => 'katalog', 9 => 'novyny']);

        $this->assertSame('novyny', $entity->get(9)->url);
        $this->assertNull($entity->get(11));
    }

    /**
     * Збирає CategoriesEntity без DI й без бази: конструктор Entity тягне сім
     * сервісів із контейнера, а get() дивиться лише у два приватні поля, які
     * заповнює initCategories(). Непорожній categoriesTree не дає йому піти в
     * базу.
     */
    private function entityWith(array $urlsById): CategoriesEntity
    {
        $categories = [];
        foreach ($urlsById as $id => $url) {
            $category = new stdClass();
            $category->id  = $id;
            $category->url = $url;
            $categories[$id] = $category;
        }

        $entity = (new ReflectionClass(CategoriesEntity::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($entity, 'allCategories'))->setValue($entity, $categories);
        (new ReflectionProperty($entity, 'categoriesTree'))->setValue($entity, [new stdClass()]);

        return $entity;
    }
}
