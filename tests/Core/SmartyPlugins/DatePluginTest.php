<?php

namespace Core\SmartyPlugins;

use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\SmartyPlugins\Plugins\Date;
use Okay\Entities\LanguagesEntity;
use Okay\Entities\TranslationsEntity;
use PHPUnit\Framework\TestCase;

/**
 * Okay\Core\SmartyPlugins\Plugins\Date treated a strtotime() result of 0 as a
 * parse failure and fell back to passing the raw string to date(), which is a
 * TypeError on PHP 8. The epoch is the one timestamp that is both valid and
 * falsy, so any record holding 1970-01-01 00:00:00 UTC took down every page
 * that rendered it - the blog list, the post page and the admin blog screen.
 *
 * Unparseable input has to stay non-fatal too: the same fallback branch is what
 * an empty or malformed stored date reaches.
 */
class DatePluginTest extends TestCase
{
    private function makePlugin(): Date
    {
        // A format argument sends run() through the translated-token branch, and
        // that branch is where the front-end fatal was raised (date('N', ...)),
        // so the language and translation entities are stubbed rather than left
        // null - otherwise the interesting path cannot be exercised at all.
        $languagesEntity = $this->createMock(LanguagesEntity::class);
        $languagesEntity->method('get')->willReturn((object)['label' => 'ua']);

        $translationsEntity = $this->createMock(TranslationsEntity::class);
        $translationsEntity->method('find')->willReturnCallback(static function () {
            $rows = [];
            foreach (['D', 'l'] as $token) {
                for ($i = 1; $i <= 7; $i++) {
                    $rows["date_{$token}_{$i}"] = (object)['value' => 'day' . $i];
                }
            }
            foreach (['S', 'F', 'FR'] as $token) {
                for ($i = 1; $i <= 12; $i++) {
                    $rows["date_{$token}_{$i}"] = (object)['value' => 'mon' . $i];
                }
            }
            return $rows;
        });

        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->method('get')->willReturnCallback(
            static function ($class) use ($languagesEntity, $translationsEntity) {
                return $class === LanguagesEntity::class ? $languagesEntity : $translationsEntity;
            }
        );

        $languages = $this->createMock(Languages::class);
        $languages->method('getLangId')->willReturn(1);

        return new Date($entityFactory, $languages);
    }

    public function testFormatsTheEpochInsteadOfFataling(): void
    {
        // Chosen so strtotime() returns exactly int(0) in the container's
        // timezone as well as in UTC - the assertion is on the round trip, not
        // on a fixed calendar day.
        $epoch = gmdate('Y-m-d H:i:s', 0);

        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame(gmdate('Y-m-d', 0), $plugin->run($epoch . ' UTC'));
    }

    /**
     * The exact call the storefront post page makes: {$post->date|date:'d m Y'}.
     * This one crashed at date('N', $time) - one line earlier than the admin's.
     */
    public function testFormatsTheEpochThroughTheTranslatedBranch(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame(gmdate('d m Y', 0), $plugin->run(gmdate('Y-m-d H:i:s', 0) . ' UTC', 'd m Y'));
    }

    public function testAcceptsAUnixTimestampAsBefore(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame(date('Y-m-d', 1234567890), $plugin->run(1234567890));
    }

    public function testFormatsAnOrdinaryDate(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame('2019-07-06', $plugin->run('2019-07-06 21:00:00'));
    }

    /**
     * @dataProvider unparseableDates
     */
    public function testUnparseableInputIsReturnedRatherThanFataling(string $input): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame($input, $plugin->run($input));
    }

    public function unparseableDates(): array
    {
        return [
            'empty string' => [''],
            'garbage' => ['not a date'],
        ];
    }

    /**
     * date() accepts null as "now" and always did here, so null is left alone -
     * it never reached the broken branch and nothing in the fix should move it.
     */
    public function testNullKeepsItsExistingMeaning(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame(date('Y-m-d'), $plugin->run(null));
    }

    /**
     * An unset `date_format` setting made date() return the empty string for
     * every unformatted call, which is what left the admin's date input blank
     * and led the blog form to save that blank back over a real date.
     */
    public function testAnUnsetSettingStillProducesADate(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('');

        $this->assertSame('06.07.2019', $plugin->run('2019-07-06 21:00:00'));
    }

    public function testAnExplicitFormatStillWinsOverTheSetting(): void
    {
        $plugin = $this->makePlugin();
        $plugin->setDateFormat('Y-m-d');

        $this->assertSame('2019', $plugin->run('2019-07-06 21:00:00', 'Y'));
    }
}
