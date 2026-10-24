<?php

namespace Core\Update;

use Okay\Core\Update\PackageFormat;
use Okay\Core\Update\ReleaseFeed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Який реліз інсталяція ставить наступним (ReleaseFeed::parseLatest з
 * установленою версією). Сайт на 1.3.9 не має потягнути 1.4.0: спершу
 * останній патч своєї гілки, і архів лише того покоління, яке він уміє.
 */
class ReleaseSelectionTest extends TestCase
{
    private const BASE = 'https://github.com/devSviat/OkayCMS-Fork/releases/download/';

    /**
     * @param string[] $zipNames null — архів першого покоління цієї версії
     * @return array<string, mixed>
     */
    private static function release(string $version, ?array $zipNames = null): array
    {
        $tag = 'okaycms-fork/v' . $version;
        $assets = [];
        foreach ($zipNames ?? [PackageFormat::zipAssetName($version, PackageFormat::LEGACY)] as $name) {
            $assets[] = ['name' => $name, 'browser_download_url' => self::BASE . $tag . '/' . $name];
        }
        foreach (['version.json', 'checksums.txt'] as $name) {
            $assets[] = ['name' => $name, 'browser_download_url' => self::BASE . $tag . '/' . $name];
        }

        return ['tag_name' => $tag, 'draft' => false, 'prerelease' => false, 'assets' => $assets];
    }

    private static function f2(string $version): array
    {
        return self::release($version, [PackageFormat::zipAssetName($version, 2)]);
    }

    /** @param array<int, array<string, mixed>> $releases */
    private static function pick(array $releases, ?string $installed): ?string
    {
        shuffle($releases);
        $latest = ReleaseFeed::parseLatest(json_encode($releases), $installed);

        return $latest['forkVersion'] ?? null;
    }

    /** @return array<int, array<string, mixed>> перехід на 1.4.0 через міст 1.3.12 */
    private static function bridgeFeed(): array
    {
        return [
            self::release('1.3.9'),
            self::release('1.3.11'),
            self::release('1.3.12'),
            self::f2('1.4.0'),
        ];
    }

    #[DataProvider('bridgeProvider')]
    public function testEveryOldInstallGoesThroughTheBridge(string $installed, string $expected): void
    {
        $this->assertSame($expected, self::pick(self::bridgeFeed(), $installed));
    }

    /** @return array<string, array{string, string}> */
    public static function bridgeProvider(): array
    {
        return [
            '1.3.9 → міст, не 1.4.0' => ['1.3.9', '1.3.12'],
            '1.3.11 → міст'          => ['1.3.11', '1.3.12'],
            'з мосту → 1.4.0'        => ['1.3.12', '1.4.0'],
            '1.2.2 → остання 1.3, не 1.4' => ['1.2.2', '1.3.12'],
            'на 1.4.0 — новішого немає' => ['1.4.0', '1.4.0'],
        ];
    }

    /** Патч старої гілки, що вийшов після 1.4.0, спершу ставиться на ній. */
    public function testLatePatchOfTheOwnLineComesFirst(): void
    {
        $feed = [...self::bridgeFeed(), self::release('1.3.13')];

        $this->assertSame('1.3.13', self::pick($feed, '1.3.12'));
        $this->assertSame('1.3.13', self::pick($feed, '1.3.9'));
        $this->assertSame('1.4.0', self::pick($feed, '1.3.13'));
    }

    /** Гілки проходяться по одній: з 1.3 на 1.4, а не одразу на 1.5. */
    public function testLinesAreCrossedOneAtATime(): void
    {
        $feed = [self::release('1.3.12'), self::f2('1.4.0'), self::f2('1.4.3'), self::f2('1.5.1')];

        $this->assertSame('1.4.3', self::pick($feed, '1.3.12'));
        $this->assertSame('1.4.3', self::pick($feed, '1.4.0'));
        $this->assertSame('1.5.1', self::pick($feed, '1.4.3'));
    }

    /** Мажор — така сама гілка: з 1.9 на найстаршу гілку 2.x, а не на найновішу. */
    public function testMajorIsCrossedThroughItsFirstLine(): void
    {
        $feed = [self::release('1.9.4'), self::f2('2.0.0'), self::f2('2.0.2'), self::f2('2.1.0')];

        $this->assertSame('2.0.2', self::pick($feed, '1.9.4'));
        $this->assertSame('2.1.0', self::pick($feed, '2.0.2'));
    }

    /** Без установленої версії (старий виклик) — просто найновіший. */
    public function testWithoutInstalledVersionTheNewestIsReturned(): void
    {
        $this->assertSame('1.4.0', self::pick(self::bridgeFeed(), null));
    }

    /**
     * Реліз лише з архівом покоління, якого інсталяція не знає, для неї не
     * існує: вона лишається на тому, що вміє, а не падає на verify.
     */
    public function testUnknownGenerationIsInvisible(): void
    {
        $next = PackageFormat::SUPPORTED + 1;
        $feed = [self::f2('1.4.0'), self::release('1.5.0', [PackageFormat::zipAssetName('1.5.0', $next)])];

        $this->assertSame('1.4.0', self::pick($feed, '1.4.0'));
        $this->assertNull(ReleaseFeed::parseLatest(json_encode([$feed[1]])));
    }

    /** Обидва архіви в одному релізі — береться найновіше покоління, яке інсталяція вміє. */
    public function testNewestSupportedGenerationWins(): void
    {
        $legacy = PackageFormat::zipAssetName('1.4.0', PackageFormat::LEGACY);
        $f2 = PackageFormat::zipAssetName('1.4.0', 2);
        $f9 = PackageFormat::zipAssetName('1.4.0', 9);

        foreach ([[$legacy, $f2, $f9], [$f9, $f2, $legacy]] as $order) {
            $latest = ReleaseFeed::parseLatest(json_encode([self::release('1.4.0', $order)]));
            $this->assertSame(2, $latest['assets']['packageFormat']);
            $this->assertStringEndsWith('/' . $f2, $latest['assets']['zip']);
        }
    }

    /**
     * Назва, яку старий код (до 1.3.12) не впізнає, — саме на цьому тримається
     * перехід через міст. Його ReleaseFeed шукає рівно цю назву.
     */
    public function testSecondGenerationNameIsNotTheLegacyName(): void
    {
        $this->assertSame('okaycms-fork-v1.4.0.zip', PackageFormat::zipAssetName('1.4.0', PackageFormat::LEGACY));
        $this->assertSame('okaycms-fork-v1.4.0-f2.zip', PackageFormat::zipAssetName('1.4.0', 2));
    }

    #[DataProvider('zipNameProvider')]
    public function testZipNameParsing(string $name, ?int $expected): void
    {
        $this->assertSame($expected, PackageFormat::ofZipAssetName($name, '1.4.0'));
    }

    /** @return array<string, array{string, ?int}> */
    public static function zipNameProvider(): array
    {
        return [
            'перше покоління'          => ['okaycms-fork-v1.4.0.zip', 1],
            'друге'                    => ['okaycms-fork-v1.4.0-f2.zip', 2],
            'двозначне'                => ['okaycms-fork-v1.4.0-f12.zip', 12],
            'f1 — не назва першого'    => ['okaycms-fork-v1.4.0-f1.zip', null],
            'нуль попереду'            => ['okaycms-fork-v1.4.0-f02.zip', null],
            'нульове'                  => ['okaycms-fork-v1.4.0-f0.zip', null],
            'чужа версія'              => ['okaycms-fork-v1.4.1-f2.zip', null],
            'хвіст після zip'          => ['okaycms-fork-v1.4.0-f2.zip.sig', null],
            'без покоління'            => ['okaycms-fork-v1.4.0-f.zip', null],
        ];
    }

    public function testUnknownGenerationCannotBeNamed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PackageFormat::zipAssetName('1.4.0', 0);
    }
}
