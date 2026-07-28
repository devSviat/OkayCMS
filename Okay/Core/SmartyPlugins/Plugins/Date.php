<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\Languages;
use Okay\Core\EntityFactory;
use Okay\Entities\TranslationsEntity;
use Okay\Entities\LanguagesEntity;
use Okay\Core\SmartyPlugins\Modifier;

class Date extends Modifier
{
    /**
     * Used only when neither the caller nor the `date_format` setting supplies a
     * format. date() returns the empty string for an empty format, which is how
     * an unset setting emptied the admin's date input - and an empty input is
     * what the blog form then tried to save back as a date.
     */
    private const FALLBACK_FORMAT = 'd.m.Y';

    private $translations;
    private $languages;
    private $langEntity;
    private $dateFormat;

    public function __construct(EntityFactory $entityFactory, Languages $languages) 
    {
        $this->translations = $entityFactory->get(TranslationsEntity::class);
        $this->langEntity   = $entityFactory->get(LanguagesEntity::class);
        $this->languages    = $languages;
        
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat   = $dateFormat;
    }
    
    public function run($date, $format = null)
    {
        // strtotime() returns int(0) for the epoch, which the old `!$time` guard
        // read as a parse failure; it then put the raw string back into $time and
        // date() raised a TypeError on PHP 8. Compare against false explicitly so
        // the one valid-but-falsy timestamp survives, and never hand date() a
        // string: input it cannot parse is returned unformatted instead.
        if (is_numeric($date)) {
            $time = (int)$date;
        } elseif ($date === null) {
            $time = null; // date() reads null as "now" - long-standing behaviour.
        } elseif (($parsed = strtotime((string)$date)) !== false) {
            $time = $parsed;
        } else {
            return (string)$date;
        }
        if ($format !== null) {
            $language = $this->langEntity->get($this->languages->getLangId());
            
            $translations = $this->translations->find(['lang' => $language->label]);
    
            $day_num = date('N', $time);
            $mon_num = date('n', $time);
            $custom_format = [
                'cD'  => addcslashes($translations["date_D_".$day_num]->value, 'A..z'), // Дни недели сокращенно
                'cl'  => addcslashes($translations["date_l_".$day_num]->value, 'A..z'), // Дни недели полностью
                'cS'  => addcslashes($translations["date_S_".$mon_num]->value, 'A..z'), // Месяцы сокращенно
                'cF'  => addcslashes($translations["date_F_".$mon_num]->value, 'A..z'), // Месяцы полностью
                'cFR' => addcslashes($translations["date_FR_".$mon_num]->value, 'A..z'), // Месяцы полностью, родительный падеж
            ];
    
            $format = strtr($format, $custom_format);
        }
        
        if (empty($format)) {
            $format = !empty($this->dateFormat) ? $this->dateFormat : self::FALLBACK_FORMAT;
        }

        return date($format, $time);
    }
}