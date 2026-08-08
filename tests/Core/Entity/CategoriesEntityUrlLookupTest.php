<?php

namespace Core\Entity;

use Okay\Entities\BlogCategoriesEntity;
use Okay\Entities\CategoriesEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Категорії та категорії блога — єдині сутності, які резолвлять обходом дерева
 * в пам'яті, а не SQL-запитом. Решта шукає в базі, де url живе в utf8mb4_general_ci, тож для
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
        foreach ([CategoriesEntity::class, BlogCategoriesEntity::class] as $class) {
            $found = $this->entityWith([7 => $storedUrl], $class)->get($lookedUpUrl);

            $this->assertNotEmpty($found, "{$class}: «{$lookedUpUrl}» мав знайти «{$storedUrl}»");
            $this->assertSame(7, $found->id);
        }
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
    public function testUnrelatedUrlIsStillNotFound(): void
    {
        foreach ([CategoriesEntity::class, BlogCategoriesEntity::class] as $class) {
            $this->assertEmpty($this->entityWith([7 => 'katalog'], $class)->get('inshyi-katalog'), $class);
        }
    }

    /**
     * Пошук за id лишається як був — за точним ключем масиву.
     */
    public function testLookupByIdIsUnaffected(): void
    {
        foreach ([CategoriesEntity::class, BlogCategoriesEntity::class] as $class) {
            $entity = $this->entityWith([7 => 'katalog', 9 => 'novyny'], $class);

            $this->assertSame('novyny', $entity->get(9)->url);
            $this->assertEmpty($entity->get(11));
        }
    }

    /**
     * Збирає CategoriesEntity без DI й без бази: конструктор Entity тягне сім
     * сервісів із контейнера, а get() дивиться лише у два приватні поля, які
     * заповнює initCategories(). Непорожній categoriesTree не дає йому піти в
     * базу.
     */
    private function entityWith(array $urlsById, string $class = CategoriesEntity::class): object
    {
        $categories = [];
        foreach ($urlsById as $id => $url) {
            $category = new stdClass();
            $category->id  = $id;
            $category->url = $url;
            $categories[$id] = $category;
        }

        $entity = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($entity, 'allCategories'))->setValue($entity, $categories);
        (new ReflectionProperty($entity, 'categoriesTree'))->setValue($entity, [new stdClass()]);

        return $entity;
    }
}
