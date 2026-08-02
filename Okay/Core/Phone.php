<?php


namespace Okay\Core;


use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

class Phone
{
    
    private $settings;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function getPhoneExample(): string
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneExample = '';
        if ($this->settings->get('phone_default_region')) {
            switch ($this->settings->get('phone_default_region')) {
                case 'UA' :
                    $phoneExample = '+380442903833';
                    break;
                case 'RU' :
                    $phoneExample = '+74996482047';
                    break;
                default:
                    $phoneExample = $phoneUtil->getExampleNumber($this->settings->get('phone_default_region'));
                    
            }
            $phoneExample = self::format($phoneExample, PhoneNumberFormat::INTERNATIONAL);
        }
        return $phoneExample;
    }
    
    /**
     * Метод подготавливает номер телефона для сохранения в базу, в базе они хранятся в стандарте E164
     * 
     * @param $phoneNumber
     * @return string
     * @throws NumberParseException
     */
    public static function toSave($phoneNumber): ?string
    {
        try {
            return self::format($phoneNumber, PhoneNumberFormat::E164);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Метод очищает телефон от всех лишних символов
     * 
     * @param $phoneNumber
     * @return string
     */
    public static function clear($phoneNumber): string
    {
        // Приводимо до рядка: сюди приходить і null - з форми без поля телефону
        // (UserRequest::postRegisterUser), і з запису БД, де phone дозволено NULL
        // (OrdersEntity, CartHelper). З PHP 8.1 null у preg_replace() - deprecated,
        // а метод оголошений як :string, тож порожній рядок і є правильним входом.
        return substr(preg_replace('~[^0-9.+]~', '', (string)$phoneNumber), 0, PhoneNumberUtil::MAX_LENGTH_FOR_NSN);
    }

    /**
     * Проверяет валидный ли номер телефона с учетом данных настроек
     * 
     * @param $phoneNumber
     * @return bool
     * @throws NumberParseException
     */
    public static function isValid($phoneNumber): bool
    {
        $wrongPrefixes = [
            '+0',
            '+89',
        ];
        
        foreach ($wrongPrefixes as $prefix) {
            if (strpos($phoneNumber, $prefix) === 0) {
                $phoneNumber = ltrim($phoneNumber, '+');
            }
        }
        
        if (empty($phoneNumber)) {
            return false;
        }
        
        if (!PhoneNumberUtil::isViablePhoneNumber($phoneNumber)) {
            return false;
        }
        
        if (($len = mb_strlen(trim($phoneNumber, '+'))) < 5 || $len > 20) {
            return false;
        }
        
        $SL = ServiceLocator::getInstance();
        /** @var Settings $settings */
        $settings = $SL->getService(Settings::class);

        $defaultRegion = $settings->get('phone_default_region');
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneObject = $phoneUtil->parse($phoneNumber, $defaultRegion);
            return $phoneUtil->isValidNumber($phoneObject);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Метод форматирует телефон в соответствии с настройками
     * 
     * @param $phoneNumber
     * @param null $numberFormat
     * @return string
     * @throws NumberParseException
     */
    public static function format($phoneNumber, $numberFormat = null): string
    {
        // Той самий null, що й у clear(): substr(null) - deprecated з PHP 8.1.
        $phoneNumber = (string)$phoneNumber;

        if (substr($phoneNumber, 0, 2) == '+0') {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (!$phoneNumber = self::clear($phoneNumber)) {
            return '';
        }

        $SL = ServiceLocator::getInstance();
        $phoneUtil = PhoneNumberUtil::getInstance();

        /** @var Settings $settings */
        $settings = $SL->getService(Settings::class);

        $defaultRegion = $settings->get('phone_default_region');
        
        if ($numberFormat === null) {
            $numberFormat = $settings->get('phone_default_format');
        }

        $phoneObject = $phoneUtil->parse($phoneNumber, $defaultRegion);
        return $phoneUtil->format($phoneObject, self::resolveFormat($numberFormat));
    }

    /**
     * З libphonenumber 9 PhoneNumberFormat — енум, а не набір int-констант.
     * Налаштування phone_default_format зберігається числом, і числом же приходить
     * з шаблонів через модифікатор |phone.
     */
    public static function resolveFormat($numberFormat): PhoneNumberFormat
    {
        if ($numberFormat instanceof PhoneNumberFormat) {
            return $numberFormat;
        }

        return PhoneNumberFormat::tryFrom((int)$numberFormat) ?? PhoneNumberFormat::E164;
    }
}