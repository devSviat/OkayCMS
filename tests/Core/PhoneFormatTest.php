<?php

namespace Core;

use libphonenumber\PhoneNumberFormat;
use Okay\Core\Phone;
use PHPUnit\Framework\TestCase;

/**
 * З libphonenumber 9 PhoneNumberFormat став енумом, а PhoneNumberUtil::format()
 * приймає тільки його. Налаштування phone_default_format зберігається числом і
 * числом приходить із шаблонів, тож перетворення числа в енум — єдине місце,
 * де переїзд може тихо зламатись.
 */
class PhoneFormatTest extends TestCase
{
    public function testStoredIntegersMapToTheSameFormatsAsBefore(): void
    {
        $this->assertSame(PhoneNumberFormat::E164, Phone::resolveFormat(0));
        $this->assertSame(PhoneNumberFormat::INTERNATIONAL, Phone::resolveFormat(1));
        $this->assertSame(PhoneNumberFormat::NATIONAL, Phone::resolveFormat(2));
        $this->assertSame(PhoneNumberFormat::RFC3966, Phone::resolveFormat(3));
    }

    public function testValuesFromTheDatabaseArriveAsStrings(): void
    {
        $this->assertSame(PhoneNumberFormat::INTERNATIONAL, Phone::resolveFormat('1'));
    }

    public function testAnEnumPassesThrough(): void
    {
        $this->assertSame(PhoneNumberFormat::NATIONAL, Phone::resolveFormat(PhoneNumberFormat::NATIONAL));
    }

    public function testUnsetOrUnknownFallsBackToE164(): void
    {
        $this->assertSame(PhoneNumberFormat::E164, Phone::resolveFormat(null));
        $this->assertSame(PhoneNumberFormat::E164, Phone::resolveFormat(''));
        $this->assertSame(PhoneNumberFormat::E164, Phone::resolveFormat(99));
    }

    /**
     * Енум не можна надрукувати в шаблоні: {libphonenumber\PhoneNumberFormat::E164}
     * дало б фатальну помилку замість числа.
     */
    public function testTheSettingsTemplateDoesNotReachForTheEnum(): void
    {
        $template = file_get_contents(__DIR__ . '/../../backend/design/html/settings_general.tpl');

        $this->assertStringNotContainsString('PhoneNumberFormat', $template);
    }
}
