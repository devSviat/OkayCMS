<?php

namespace Admin;

use Okay\Admin\Requests\BackendAuthorsRequest;
use Okay\Admin\Requests\BackendBlogCategoriesRequest;
use Okay\Admin\Requests\BackendBlogRequest;
use Okay\Admin\Requests\BackendBrandsRequest;
use Okay\Admin\Requests\BackendCategoriesRequest;
use Okay\Admin\Requests\BackendFeaturesRequest;
use Okay\Admin\Requests\BackendPagesRequest;
use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Core\Request;
use Okay\Core\Translit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * MySQL порівнює url у _ci: `Item-1` і `item-1` для бази це один рядок, а для
 * PHP — два. На цьому розходженні ok_router_cache і набирав 1062 Duplicate
 * entry.
 *
 * Два з трьох входів регістр зводили й раніше — автогенерація з назви та
 * CSV-імпорт, обидва через Translit::translit(). Незакритим лишався ручний
 * ввід у форму; його й закриваємо, на будь-якому збереженні.
 *
 * Чому в реквесті, а не глибше: нижче по стеку значення читають ДО запису
 * (цикл унікальності в CategoriesEntity::add, валідатори), і згортання після
 * них проґавило б дубль. Ціна рішення й перевірка перед викаткою —
 * docs/UPGRADE-urls.md.
 */
class BackendRequestUrlCaseTest extends TestCase
{
    #[DataProvider('requestProvider')]
    public function testPostedUrlIsLowercased(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, 'PRODUCT-0B200-03580600');

        $this->assertSame('product-0b200-03580600', $result->url);
    }

    /**
     * Наявна сутність нормалізується так само, як нова. Наслідок, прийнятий
     * свідомо: поле url у картці readonly, але браузер readonly-поле все одно
     * надсилає, тож сутність зі старим mixed-case урлом переїде на нижній
     * регістр при першому ж збереженні картки — і в магазині, де такі url є,
     * це зміна адрес.
     *
     * Альтернатива — не чіпати наявні — лишає такий url назавжди, а разом із
     * ним і розходження з _ci-порівнянням у базі, заради усунення якого все
     * це й робиться.
     */
    #[DataProvider('requestProvider')]
    public function testExistingEntityUrlIsLowercasedToo(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, 'Legacy-MixedCase-1', ['id' => 7]);

        $this->assertSame('legacy-mixedcase-1', $result->url);
    }

    /**
     * strtolower не бере кирилицю — потрібна mb-версія, інакше кириличні url
     * лишаться в змішаному регістрі і проблема повернеться саме для них.
     */
    #[DataProvider('requestProvider')]
    public function testCyrillicUrlIsLowercased(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, 'КАТЕГОРІЯ-Тест');

        $this->assertSame('категорія-тест', $result->url);
    }

    /**
     * Пробіли по краях знімались і раніше — нормалізація не має це ламати, і
     * має поводитись однаково для нової та наявної сутності.
     */
    #[DataProvider('requestProvider')]
    public function testSurroundingSpacesAreStillRemoved(string $requestClass, string $method): void
    {
        $this->assertSame('item-1', $this->post($requestClass, $method, '  Item-1  ')->url);
        $this->assertSame('item-1', $this->post($requestClass, $method, '  Item-1  ', ['id' => 7])->url);
    }

    #[DataProvider('requestProvider')]
    public function testAlreadyLowercaseUrlIsUnchanged(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, 'product-0b200-03580600');

        $this->assertSame('product-0b200-03580600', $result->url);
    }

    /**
     * Порожнє поле url — звичайний випадок: частина сутностей добудовує його з
     * назви. Приведення регістру не має на цьому падати й не має видавати
     * значення у змішаному регістрі.
     */
    #[DataProvider('requestProvider')]
    public function testMissingUrlYieldsLowercaseWithoutErrors(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, null, ['name' => 'Тестова назва']);

        $this->assertIsString($result->url);
        $this->assertSame(mb_strtolower($result->url, 'UTF-8'), $result->url);
    }

    /**
     * url[] замість url приходить із будь-якого нестандартного POST — від
     * дубльованого input із `[]` у name до шаблону, розширеного модулем.
     * Хай там що, у сутність не має потрапити ані літерал `array`, ані масив:
     * url — це alternativeIdField, і сміття в ньому забирає в сутності адресу.
     */
    #[DataProvider('requestProvider')]
    public function testArrayUrlNeverReachesTheEntity(string $requestClass, string $method): void
    {
        $result = $this->post($requestClass, $method, null, ['url' => ['first-1', 'second-2']]);

        // Очікування різні, і це не випадковість. Шість реквестів беруть url
        // типом 'string', а Request::post() із будь-яким типом згортає масив до
        // першого елемента ще до реквеста. Сторінки типу не мають — він вирізав
        // би слеш, — тож масив там відсікає is_string() і url лишається
        // порожнім, після чого валідатор знайде за ним головну сторінку й
        // поверне url_exists. Обидві поведінки безпечні, але різні, і
        // перевіряти їх треба точним значенням: assertNotSame('array') для
        // шести з семи істинний за побудовою й не стереже нічого.
        $expected = $requestClass === BackendPagesRequest::class ? '' : 'first-1';

        $this->assertSame($expected, $result->url);
    }

    /**
     * У сторінок url законно буває складеним: `user/login`, `user/register`,
     * `user/password_remind` — усі три лежать у стоковій базі, і жодного з них
     * немає в PagesEntity::getSystemPages(), тобто від перейменування вони не
     * захищені нічим.
     *
     * Через це postPage() — єдиний із семи, хто не може взяти url через
     * post('url', 'string'): whitelist того типу
     * (/[^\p{L}\p{Nd}\d\s_\-.%]/ui) вирізає слеш. Менеджер відкрив би картку
     * «Вхід», зберіг би її не змінюючи — і сторінка стала б `userlogin`.
     */
    public function testPageUrlKeepsItsSlashes(): void
    {
        foreach (['user/login', 'user/register', 'user/password_remind'] as $url) {
            $result = $this->post(BackendPagesRequest::class, 'postPage', $url, ['id' => 7]);

            $this->assertSame($url, $result->url);
        }
    }

    /**
     * Слеш має пережити й створення, де регістр зводиться.
     */
    public function testPageUrlKeepsSlashesOnCreateWhileFoldingCase(): void
    {
        $result = $this->post(BackendPagesRequest::class, 'postPage', 'User/Login-Test');

        $this->assertSame('user/login-test', $result->url);
    }

    public static function requestProvider(): array
    {
        return [
            'products'        => [BackendProductsRequest::class,       'postProduct'],
            'categories'      => [BackendCategoriesRequest::class,     'postCategory'],
            'blog'            => [BackendBlogRequest::class,           'postArticle'],
            'blog_categories' => [BackendBlogCategoriesRequest::class, 'postCategory'],
            'brands'          => [BackendBrandsRequest::class,         'postBrand'],
            'pages'           => [BackendPagesRequest::class,          'postPage'],
            'authors'         => [BackendAuthorsRequest::class,        'postAuthor'],
        ];
    }

    /**
     * Характеристики в загальний список не входять: postFeature() зводить url
     * у нижній регістр сам і жорсткіше за решту —
     * strtolower(preg_replace("/[^0-9a-z]+/ui", '', $url)), тобто url
     * характеристики принципово буквено-цифровий, без дефісів. Ще одне
     * приведення там було б мертвим кодом, а спільні очікування цього тесту до
     * нього незастосовні. Тест стоїть, щоб ця властивість не загубилась
     * непомітно.
     */
    public function testFeatureUrlIsAlreadyNormalizedByItsOwnRules(): void
    {
        $result = $this->post(BackendFeaturesRequest::class, 'postFeature', 'PRODUCT-0B200');

        $this->assertSame('product0b200', $result->url);
    }

    /**
     * Реквести збираються без конструктора й отримують Request через
     * рефлексію: сигнатури конструкторів у них різні, а для розбору url
     * потрібен лише він. Сам Request теж без конструктора — так само, як у
     * tests/Core/RequestTest.php.
     */
    private function post(string $requestClass, string $method, ?string $url, array $extraPost = []): object
    {
        // updated_date — не про url: без нього BackendBlogRequest зве
        // strtotime(null) і роняє прогін на deprecation, до цієї правки
        // відношення не має.
        $extraPost += ['updated_date' => '2026-08-07'];

        $_POST = $url === null ? $extraPost : ['url' => $url] + $extraPost;

        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $subject = (new ReflectionClass($requestClass))->newInstanceWithoutConstructor();

        (new ReflectionProperty($subject, 'request'))->setValue($subject, $request);

        // Блог і характеристики добудовують url із назви, коли поле порожнє.
        if (property_exists($subject, 'translit')) {
            (new ReflectionProperty($subject, 'translit'))->setValue($subject, new Translit());
        }

        try {
            return $subject->$method();
        } finally {
            $_POST = [];
        }
    }
}
