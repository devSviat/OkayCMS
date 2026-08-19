<?php


namespace Okay\Core;


use Okay\Core\Modules\Modules;
use Psr\Log\LoggerInterface;

#[\AllowDynamicProperties]
class BackendTranslations
{
    
    private $_logger;
    private $_modules;
    private $_initializedLang;
    private $_debugTranslation;
    private $_langEn;
    
    public function __construct(LoggerInterface $logger, Modules $modules, $debugTranslation = false)
    {
        $this->_logger = $logger;
        $this->_modules = $modules;
        $this->_debugTranslation = (bool)$debugTranslation;
    }
    
    public function getLangLabel()
    {
        return $this->_initializedLang;
    }
    
    /**
     * Мітки мов, для яких у backend/lang/ є файл перекладу.
     *
     * @return string[]
     */
    public function availableLanguages()
    {
        $labels = [];

        foreach (glob('backend/lang/??.php') as $file) {
            $labels[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $labels;
    }

    /**
     * Перша з бажаних мов, переклад якої існує.
     *
     * Мови магазину й мови адмінки - різні набори: у магазині може стояти
     * грузинська, для якої файла перекладу немає. Тому вибір явний, а не через
     * відкат усередині initTranslations().
     *
     * @param array $preferred мітки за спаданням пріоритету; одна з них приходить
     *                          із куки, тож рядками вони бути не зобов'язані
     * @return string
     */
    public function resolveLang(array $preferred)
    {
        $available = $this->availableLanguages();

        foreach ($preferred as $label) {
            if (is_string($label) && in_array($label, $available, true)) {
                return $label;
            }
        }

        if (in_array('en', $available, true) || $available === []) {
            return 'en';
        }

        return reset($available);
    }

    public function initTranslations($langLabel = 'en')
    {
        if ($this->_initializedLang === $langLabel) {
            return;
        }
        // Перевод админки
        $lang = [];
        $file = "backend/lang/" .$langLabel . ".php";
        if (!file_exists($file)) {
            foreach (glob("backend/lang/??.php") as $f) {
                $file = "backend/lang/" . pathinfo($f, PATHINFO_FILENAME) . ".php";
                break;
            }
        }
        require_once($file);
        foreach ($lang as $var=>$translation) {
            $this->addTranslation($var, $translation);
        }

        foreach ($this->_modules->getRunningModules() as $runningModule) {
            foreach ($this->_modules->getModuleBackendTranslations($runningModule['vendor'], $runningModule['module_name'], $langLabel) as $var => $translation) {
                $this->addTranslation($var, $translation);
            }
        }

        $this->_initializedLang = $langLabel;
    }

    public function __get($var)
    {
        if (empty($this->_langEn)) {
            $lang = [];
            require_once("backend/lang/en.php");
            $this->_langEn = $lang;
        }
        
        if (isset($this->_langEn[$var])) {
            $this->$var = $translation = $this->_langEn[$var];

            // Если включили дебаг переводов, выведим соответствующее сообщение на неизвестный перевод
            if ($this->_debugTranslation === true) {
                $translation .= '<b style="color: red!important;">$btr->' . $var . ' from other language</b>';
            }
            return $translation;
        } elseif ($this->_debugTranslation === true) {
            return '<b style="color: red!important;">$btr->' . $var . ' not exists</b>';
        }
    }
    
    public function getTranslation($var)
    {
        if (isset($this->$var) && !is_object($this->$var)) {
            return $this->$var;
        } else {
            return false;
        }
    }

    /**
     * @param $var
     * @param $translation
     * добавление перевода к уже существующему набору
     */
    public function addTranslation($var, $translation)
    {
        $var = preg_replace('~[^\w]~', '', $var);
        $this->$var = $translation;
    }
}
