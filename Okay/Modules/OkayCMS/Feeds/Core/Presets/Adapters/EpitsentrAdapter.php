<?php

namespace Okay\Modules\OkayCMS\Feeds\Core\Presets\Adapters;

use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\QueryFactory\Select;
use Okay\Core\Translit as TranslitHelper;
use Okay\Entities\BrandsEntity;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\FeaturesEntity;
use Okay\Entities\FeaturesValuesEntity;
use Okay\Entities\LanguagesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\OkayCMS\Feeds\Core\Presets\AbstractPresetAdapter;

class EpitsentrAdapter extends AbstractPresetAdapter
{
    /** @var string */
    static protected $headerTemplate = 'presets/epitsentr/header.tpl';

    /** @var string */
    static protected $footerTemplate = 'presets/epitsentr/footer.tpl';

    /** @var TranslitHelper */
    private $translit;

    /** @var array Кеш налаштувань категорій */
    private $categorySettingsCache = [];

    /** @var array Кеш налаштувань features */
    private $featureSettingsCache = [];

    /** @var bool Прапорець для HTML описів */
    private $isHtmlDescription;

    /** @var bool Прапорець для повного опису */
    private $useFullDescription;

    /** @var int|null ID мови ru */
    private $ruLangId;

    /** @var int|null ID мови ua */
    private $uaLangId;

    /** @var string Назва сайту для ru мови */
    private $siteNameRu;

    /** @var string Назва сайту для ua мови */
    private $siteNameUa;

    /** @var array Категорії для ru мови */
    private $allCategoriesRu;

    /** @var array Категорії для ua мови */
    private $allCategoriesUa;

    /** @var object Шаблон SEO для ru мови */
    private $defaultProductsSeoPatternRu;

    /** @var object Шаблон SEO для ua мови */
    private $defaultProductsSeoPatternUa;

    protected function init(): void
    {
        parent::init();
        
        // Знаходимо мови ru та ua
        $allLanguages = $this->languages->getAllLanguages();
        foreach ($allLanguages as $lang) {
            if ($lang->label === 'ru') {
                $this->ruLangId = $lang->id;
            } elseif ($lang->label === 'ua') {
                $this->uaLangId = $lang->id;
            }
        }
        
        // Отримуємо Translit через ServiceLocator
        $this->translit = \Okay\Core\ServiceLocator::getInstance()->getService(TranslitHelper::class);
        
        // Отримуємо дані для російської версії
        if ($this->ruLangId !== null) {
            $currentLangId = $this->languages->getLangId();
            $this->languages->setLangId($this->ruLangId);
            $this->settings->initSettings();
            
            $this->siteNameRu = $this->settings->get('site_name');
            $this->defaultProductsSeoPatternRu = (object)$this->settings->get('default_products_seo_pattern');
            $this->categoriesEntity->initCategories();
            $this->allCategoriesRu = $this->categoriesEntity->find();
            
            $this->languages->setLangId($currentLangId);
            $this->settings->initSettings();
            $this->categoriesEntity->initCategories();
        }
        
        // Отримуємо дані для української версії
        if ($this->uaLangId !== null) {
            $currentLangId = $this->languages->getLangId();
            $this->languages->setLangId($this->uaLangId);
            $this->settings->initSettings();
            
            $this->siteNameUa = $this->settings->get('site_name');
            $this->defaultProductsSeoPatternUa = (object)$this->settings->get('default_products_seo_pattern');
            $this->categoriesEntity->initCategories();
            $this->allCategoriesUa = $this->categoriesEntity->find();
            
            $this->languages->setLangId($currentLangId);
            $this->settings->initSettings();
            $this->categoriesEntity->initCategories();
        }
    }

    public function render($feed): void
    {
        $this->feed = $feed;
        $feed->settings = $this->loadSettings($feed->settings);
        
        // Кешуємо налаштування для швидкого доступу
        $this->isHtmlDescription = !empty($feed->settings['description_in_html']) && $feed->settings['description_in_html'] == 1;
        $this->useFullDescription = !empty($feed->settings['use_full_description']);
        
        // Викликаємо батьківський render
        parent::render($feed);
    }

    public function getQuery($feedId): Select
    {
        $sql = parent::getQuery(...func_get_args());

        if ($this->useFullDescription) {
            $sql->cols(['lp.description AS description']);
        } else {
            $sql->cols(['lp.annotation AS annotation']);
        }

        // Додаємо переклади для ru та ua мов (оптимізовано - уникаємо повторних перевірок)
        if ($this->ruLangId !== null) {
            if ($this->useFullDescription) {
                $sql->cols([
                    'lp_ru.name as product_name_ru',
                    'lp_ru.description as description_ru',
                    'lv_ru.name as variant_name_ru',
                ]);
            } else {
                $sql->cols([
                    'lp_ru.name as product_name_ru',
                    'lp_ru.annotation as annotation_ru',
                    'lv_ru.name as variant_name_ru',
                ]);
            }
            $sql->leftJoin(ProductsEntity::getLangTable().' AS lp_ru', 'lp_ru.product_id = t.product_id and lp_ru.lang_id=' . $this->ruLangId);
            $sql->leftJoin(VariantsEntity::getLangTable().' AS lv_ru', 'lv_ru.variant_id = t.variant_id and lv_ru.lang_id=' . $this->ruLangId);
        }

        if ($this->uaLangId !== null) {
            if ($this->useFullDescription) {
                $sql->cols([
                    'lp_ua.name as product_name_ua',
                    'lp_ua.description as description_ua',
                    'lv_ua.name as variant_name_ua',
                ]);
            } else {
                $sql->cols([
                    'lp_ua.name as product_name_ua',
                    'lp_ua.annotation as annotation_ua',
                    'lv_ua.name as variant_name_ua',
                ]);
            }
            $sql->leftJoin(ProductsEntity::getLangTable().' AS lp_ua', 'lp_ua.product_id = t.product_id and lp_ua.lang_id=' . $this->uaLangId);
            $sql->leftJoin(VariantsEntity::getLangTable().' AS lv_ua', 'lv_ua.variant_id = t.variant_id and lv_ua.lang_id=' . $this->uaLangId);
        }

        return ExtenderFacade::execute(__METHOD__, $sql, func_get_args());
    }

    protected function getSubSelect($feedId): Select
    {
        $sql = parent::getSubSelect(...func_get_args());

        $sql->cols([
            'v.compare_price',
            'v.weight',
        ]);

        // Додаємо translit для значень features (lfv вже доступний через joinFeatures в parent)
        $sql->cols([
            'GROUP_CONCAT(DISTINCT fv.feature_id, "!-", IFNULL(lfv.translit, "") SEPARATOR "@|@") AS translit_string',
        ]);

        if ($this->feed->settings['upload_only_products_in_stock']) {
            $sql->where('(v.stock >0 OR v.stock is NULL)');
        }

        if (!$this->feed->settings['upload_without_images']) {
            $sql->where('p.main_image_id != \'\' AND p.main_image_id IS NOT NULL');
        }

        if ($this->feed->settings['no_export_without_price']) {
            $sql->where('v.price > 0');
        }

        if (($value = $this->feed->settings['filter_price']['value']) !== null) {
            $operator = $this->normalizeComparisonOperator($this->feed->settings['filter_price']['operator']);

            $sql->join('left', CurrenciesEntity::getTable() . ' AS cur', 'cur.id = v.currency_id')
                ->where("(v.price*cur.rate_to/cur.rate_from) {$operator} :filter_price_value")
                ->bindValues(['filter_price_value' => $value]);
        }

        if (($value = $this->feed->settings['filter_stock']['value']) !== null) {
            $operator = $this->normalizeComparisonOperator($this->feed->settings['filter_stock']['operator']);

            $sql->where("IF(v.stock IS NULL, IF ('{$operator}' = '<' OR '{$operator}' = '=', false, true), v.stock {$operator} :filter_stock_value)")
                ->bindValues(['filter_stock_value' => $value]);
        }

        return ExtenderFacade::execute(__METHOD__, $sql, func_get_args());
    }

    public function modifyItem(object $item): object
    {
        $item = parent::modifyItem($item);
        
        // Парсимо translit_string і додаємо translit до features
        if (!empty($item->translit_string) && !empty($item->features)) {
            $translits = [];
            foreach (explode('@|@', $item->translit_string) as $translitItem) {
                list($featureId, $translit) = explode('!-', $translitItem, 2);
                if (!empty($translit) && isset($item->features[$featureId])) {
                    $translits[$featureId] = $translit;
                }
            }
            
            // Додаємо translit до features
            foreach ($translits as $featureId => $translit) {
                if (isset($item->features[$featureId])) {
                    $item->features[$featureId]['translit'] = $translit;
                }
            }
        }
        
        // Застосовуємо шаблон опису товару на російській мові
        if ($this->ruLangId !== null) {
            $metaParts = $this->getMetadataPartsRu($item);
            $item = $this->xmlFeedHelper->attachDescriptionByTemplate(
                $item,
                $metaParts,
                $this->getDescriptionTemplateRu($item),
                'description_ru'
            );
            $item = $this->xmlFeedHelper->attachDescriptionByTemplate(
                $item,
                $metaParts,
                $this->getAnnotationTemplateRu($item),
                'annotation_ru'
            );
        }
        
        // Застосовуємо шаблон опису товару на українській мові
        if ($this->uaLangId !== null) {
            $metaParts = $this->getMetadataPartsUa($item);
            $item = $this->xmlFeedHelper->attachDescriptionByTemplate(
                $item,
                $metaParts,
                $this->getDescriptionTemplateUa($item),
                'description_ua'
            );
            $item = $this->xmlFeedHelper->attachDescriptionByTemplate(
                $item,
                $metaParts,
                $this->getAnnotationTemplateUa($item),
                'annotation_ua'
            );
        }
        
        return $item;
    }

    protected function getDescriptionTemplateRu($product): string
    {
        if (!empty($product->main_category_id) && isset($this->allCategoriesRu[$product->main_category_id])) {
            $category = $this->allCategoriesRu[$product->main_category_id];
            $descriptionTemplate = '';
            if ($data = $this->xmlFeedHelper->getCategoryField($category, 'auto_description')) {
                $descriptionTemplate = $data;
            } elseif (!empty($this->defaultProductsSeoPatternRu->auto_description)) {
                $descriptionTemplate = $this->defaultProductsSeoPatternRu->auto_description;
            }
            return $descriptionTemplate;
        }
        return '';
    }

    protected function getAnnotationTemplateRu($product): string
    {
        if (!empty($product->main_category_id) && isset($this->allCategoriesRu[$product->main_category_id])) {
            $category = $this->allCategoriesRu[$product->main_category_id];
            $annotationTemplate = '';
            if ($data = $this->xmlFeedHelper->getCategoryField($category, 'auto_annotation')) {
                $annotationTemplate = $data;
            } elseif (!empty($this->defaultProductsSeoPatternRu->auto_annotation)) {
                $annotationTemplate = $this->defaultProductsSeoPatternRu->auto_annotation;
            }
            return $annotationTemplate;
        }
        return '';
    }

    protected function getDescriptionTemplateUa($product): string
    {
        if (!empty($product->main_category_id) && isset($this->allCategoriesUa[$product->main_category_id])) {
            $category = $this->allCategoriesUa[$product->main_category_id];
            $descriptionTemplate = '';
            if ($data = $this->xmlFeedHelper->getCategoryField($category, 'auto_description')) {
                $descriptionTemplate = $data;
            } elseif (!empty($this->defaultProductsSeoPatternUa->auto_description)) {
                $descriptionTemplate = $this->defaultProductsSeoPatternUa->auto_description;
            }
            return $descriptionTemplate;
        }
        return '';
    }

    protected function getAnnotationTemplateUa($product): string
    {
        if (!empty($product->main_category_id) && isset($this->allCategoriesUa[$product->main_category_id])) {
            $category = $this->allCategoriesUa[$product->main_category_id];
            $annotationTemplate = '';
            if ($data = $this->xmlFeedHelper->getCategoryField($category, 'auto_annotation')) {
                $annotationTemplate = $data;
            } elseif (!empty($this->defaultProductsSeoPatternUa->auto_annotation)) {
                $annotationTemplate = $this->defaultProductsSeoPatternUa->auto_annotation;
            }
            return $annotationTemplate;
        }
        return '';
    }

    protected function getMetadataPartsRu($product): array
    {
        $metaDataParts = $this->xmlFeedHelper->getMetadataParts($product);

        if (!empty($product->brand_name)) {
            $metaDataParts['{$brand}'] = $product->brand_name;
        }

        if (!empty($product->product_name_ru)) {
            $metaDataParts['{$product}'] = $product->product_name_ru;
        } elseif (!empty($product->product_name)) {
            $metaDataParts['{$product}'] = $product->product_name;
        }

        if (!empty($this->siteNameRu)) {
            $metaDataParts['{$sitename}'] = $this->siteNameRu;
        }

        if (!empty($product->main_category_id) && isset($this->allCategoriesRu[$product->main_category_id])) {
            $category = $this->allCategoriesRu[$product->main_category_id];
            $metaDataParts['{$category}'] = ($category->name ?: '');
            $metaDataParts['{$category_h1}'] = ($category->name_h1 ?: '');
        }

        return $metaDataParts;
    }

    protected function getMetadataPartsUa($product): array
    {
        $metaDataParts = $this->xmlFeedHelper->getMetadataParts($product);

        if (!empty($product->brand_name)) {
            $metaDataParts['{$brand}'] = $product->brand_name;
        }

        if (!empty($product->product_name_ua)) {
            $metaDataParts['{$product}'] = $product->product_name_ua;
        } elseif (!empty($product->product_name)) {
            $metaDataParts['{$product}'] = $product->product_name;
        }

        if (!empty($this->siteNameUa)) {
            $metaDataParts['{$sitename}'] = $this->siteNameUa;
        }

        if (!empty($product->main_category_id) && isset($this->allCategoriesUa[$product->main_category_id])) {
            $category = $this->allCategoriesUa[$product->main_category_id];
            $metaDataParts['{$category}'] = ($category->name ?: '');
            $metaDataParts['{$category_h1}'] = ($category->name_h1 ?: '');
        }

        return $metaDataParts;
    }

    public function getItem(object $product, bool $addVariantUrl = false): array
    {
        $result = [];

        //<price>&<price_old> - оптимізовано
        $price = $product->price;
        $comparePrice = $product->compare_price ?? 0;
        
        // Конвертація валюти (оптимізовано - уникаємо зайвих перевірок)
        if (isset($this->allCurrencies[$product->currency_id])) {
            $variantCurrency = $this->allCurrencies[$product->currency_id];
            if ($variantCurrency->rate_from != $variantCurrency->rate_to) {
                $rateMultiplier = $variantCurrency->rate_to / $variantCurrency->rate_from;
                $price = round($price * $rateMultiplier, 2);
                if ($comparePrice > 0) {
                    $comparePrice = round($comparePrice * $rateMultiplier, 2);
                }
            }
        }

        // Зміна ціни (якщо встановлено)
        if (!empty($this->feed->settings['price_change'])) {
            $priceChangeMultiplier = 1 + ($this->feed->settings['price_change'] / 100);
            $price *= $priceChangeMultiplier;
            if ($comparePrice > 0) {
                $comparePrice *= $priceChangeMultiplier;
            }
        }

        $result['price']['data'] = $this->money->convert($price, $this->mainCurrency->id, false);
        if ($comparePrice > 0) {
            $result['price_old']['data'] = $this->money->convert($comparePrice, $this->mainCurrency->id, false);
        }

        //<availability> - визначаємо статус наявності товару
        $availability = 'out_of_stock';
        if ($product->stock > 0 || $product->stock === null) {
            $availability = 'in_stock';
        } elseif ($product->stock == 0) {
            $availability = 'under_the_order';
        }
        $result['availability']['data'] = $availability;

        //<category code="..."> та <attribute_set code="..."> - оптимізовано (один виклик getCategorySettings)
        if (!empty($product->main_category_id)) {
            $categoryId = $product->main_category_id;
            
            // Кешуємо налаштування категорії
            if (!isset($this->categorySettingsCache[$categoryId])) {
                $this->categorySettingsCache[$categoryId] = $this->getCategorySettings($categoryId);
            }
            $categorySettings = $this->categorySettingsCache[$categoryId];
            
            $categoryCode = $categoryId;
            $categoryName = '';
            
            // Використовуємо зовнішній ID категорії, якщо він вказаний в налаштуваннях
            if ($categorySettings && !empty($categorySettings['external_id'])) {
                $categoryCode = $categorySettings['external_id'];
            }
            
            // Отримуємо назву категорії
            if ($categorySettings && !empty($categorySettings['name_in_feed'])) {
                $categoryName = $categorySettings['name_in_feed'];
            }
            if (empty($categoryName) && isset($this->allCategories[$categoryId])) {
                $categoryName = $this->allCategories[$categoryId]->name;
            }
            
            if (!empty($categoryName)) {
                $escapedName = $this->xmlFeedHelper->escape($categoryName);
                $result[] = [
                    'tag' => 'category',
                    'data' => $escapedName,
                    'attributes' => ['code' => $categoryCode],
                ];
                
                // attribute_set використовує ті ж дані
                $result[] = [
                    'tag' => 'attribute_set',
                    'data' => $escapedName,
                    'attributes' => ['code' => $categoryCode],
                ];
            }
        }


        //<count>
        if ($this->feed->settings['count']) {
            $result['count']['data'] = $product->stock ?? $this->settings->get('max_order_amount');
        }


        //<picture> - оптимізовано
        if (!empty($product->images)) {
            $images = $product->images;
            $imagesCount = count($images);
            $maxImages = $imagesCount > 11 ? 11 : $imagesCount;
            
            for ($i = 0; $i < $maxImages; $i++) {
                $result[] = [
                    'tag' => 'picture',
                    'data' => $this->image->getResizeModifier($images[$i], 1200, 1200),
                ];
            }
        }


        // $result['manufacturer_warranty']['data'] = $this->feed->settings['has_manufacturer_warranty'] ? 'true' : 'false';



        //<name lang="ru"> та <name lang="ua"> - оптимізовано
        // Формуємо базову назву один раз
        $baseName = $product->product_name ?? '';
        $baseVariantName = $product->variant_name ?? '';
        $baseFullName = $baseName . ($baseVariantName ? ' ' . $baseVariantName : '');
        
        // Назва ru
        $nameRu = '';
        if (!empty($product->product_name_ru)) {
            $variantRu = $product->variant_name_ru ?? '';
            $nameRu = $product->product_name_ru . ($variantRu ? ' ' . $variantRu : '');
        } else {
            $nameRu = $baseFullName;
        }
        if ($nameRu) {
            $result[] = [
                'tag' => 'name',
                'data' => $this->xmlFeedHelper->escape($nameRu),
                'attributes' => ['lang' => 'ru'],
            ];
        }

        // Назва ua
        $nameUa = '';
        if (!empty($product->product_name_ua)) {
            $variantUa = $product->variant_name_ua ?? '';
            $nameUa = $product->product_name_ua . ($variantUa ? ' ' . $variantUa : '');
        } else {
            $nameUa = $baseFullName;
        }
        if ($nameUa) {
            $result[] = [
                'tag' => 'name',
                'data' => $this->xmlFeedHelper->escape($nameUa),
                'attributes' => ['lang' => 'ua'],
            ];
        }
        


        //<description lang="ru"> та <description lang="ua"> - оптимізовано
        // Опис ru
        $descriptionRu = '';
        if (!empty($product->description_ru)) {
            $descriptionRu = $product->description_ru;
        } elseif (!empty($product->annotation_ru)) {
            $descriptionRu = $product->annotation_ru;
        } elseif (!empty($product->description)) {
            $descriptionRu = $product->description;
        } elseif (!empty($product->annotation)) {
            $descriptionRu = $product->annotation;
        }
        
        if (!empty($descriptionRu)) {
            // Якщо HTML, передаємо текст повністю в CDATA (згідно з документацією Epitsentr)
            if ($this->isHtmlDescription) {
                $descriptionRu = substr($descriptionRu, 0, 12160);
                $result[] = [
                    'tag' => 'description',
                    'data' => '<![CDATA[' . $descriptionRu . ']]>',
                    'attributes' => ['lang' => 'ru'],
                ];
            } else {
                // Передаємо опис без верстки
                $descriptionRu = $this->xmlFeedHelper->escape(substr($descriptionRu, 0, 12160));
                $result[] = [
                    'tag' => 'description',
                    'data' => $descriptionRu,
                    'attributes' => ['lang' => 'ru'],
                ];
            }
        }

        // Опис ua
        $descriptionUa = '';
        if (!empty($product->description_ua)) {
            $descriptionUa = $product->description_ua;
        } elseif (!empty($product->annotation_ua)) {
            $descriptionUa = $product->annotation_ua;
        } elseif (!empty($product->description)) {
            $descriptionUa = $product->description;
        } elseif (!empty($product->annotation)) {
            $descriptionUa = $product->annotation;
        }
        
        if (!empty($descriptionUa)) {
            // Якщо HTML, передаємо текст повністю в CDATA (згідно з документацією Epitsentr)
            if ($this->isHtmlDescription) {
                $descriptionUa = substr($descriptionUa, 0, 12160);
                $result[] = [
                    'tag' => 'description',
                    'data' => '<![CDATA[' . $descriptionUa . ']]>',
                    'attributes' => ['lang' => 'ua'],
                ];
            } else {
                // Передаємо опис без верстки
                $descriptionUa = $this->xmlFeedHelper->escape(substr($descriptionUa, 0, 12160));
                $result[] = [
                    'tag' => 'description',
                    'data' => $descriptionUa,
                    'attributes' => ['lang' => 'ua'],
                ];
            }
        }


        //<vendor code="..."> - оптимізовано
        if (!empty($product->brand_name)) {
            $vendorCode = '';
            
            // Перевіряємо чи є зовнішній ID бренду в налаштуваннях
            $brandExternalId = null;
            if (!empty($product->brand_id) && isset($this->feed->settings['brands_external_ids'][$product->brand_id])) {
                $brandExternalId = trim($this->feed->settings['brands_external_ids'][$product->brand_id]);
            }
            
            if (!empty($brandExternalId)) {
                $vendorCode = $brandExternalId;
            } else {
                $vendorCode = strtolower($this->translit->translitAlpha($product->brand_name));
            }
            
            $result[] = [
                'tag' => 'vendor',
                'data' => $this->xmlFeedHelper->escape($product->brand_name),
                'attributes' => $vendorCode ? ['code' => $this->xmlFeedHelper->escape($vendorCode)] : [],
            ];
        }

        //<country_of_origin code="..."> - оптимізовано з кешуванням
        $countryOfOriginParamId = $this->feed->settings['country_of_origin'] ?? null;
        if ($countryOfOriginParamId && isset($product->features[$countryOfOriginParamId])) {
            $countryFeature = $product->features[$countryOfOriginParamId];
            $countryValue = $countryFeature['values_string'];
            $countryCode = '';
            
            // Кешуємо налаштування feature
            if (!isset($this->featureSettingsCache[$countryOfOriginParamId])) {
                $this->featureSettingsCache[$countryOfOriginParamId] = $this->getFeatureSettings($countryOfOriginParamId);
            }
            $featureSettings = $this->featureSettingsCache[$countryOfOriginParamId];
            
            if (!empty($featureSettings['code'])) {
                $countryCode = $featureSettings['code'];
            } elseif (!empty($countryFeature['translit'])) {
                // Використовуємо translit з бази даних
                $countryCode = strtolower(substr($countryFeature['translit'], 0, 3));
            }
            
            $result[] = [
                'tag' => 'country_of_origin',
                'data' => $this->xmlFeedHelper->escape($countryValue),
                'attributes' => $countryCode ? ['code' => $this->xmlFeedHelper->escape($countryCode)] : [],
            ];
            unset($product->features[$countryOfOriginParamId]);
        }


        //<param> - оптимізовано з кешуванням налаштувань
        if (!empty($product->features)) {
            foreach ($product->features as $feature) {
                $featureId = $feature['id'];
                
                // Кешуємо налаштування feature
                if (!isset($this->featureSettingsCache[$featureId])) {
                    $this->featureSettingsCache[$featureId] = $this->getFeatureSettings($featureId);
                }
                $featureSettings = $this->featureSettingsCache[$featureId];

                if (!$featureSettings || $featureSettings['to_feed']) {
                    $name = ($featureSettings && !empty($featureSettings['name_in_feed'])) 
                        ? $featureSettings['name_in_feed'] 
                        : $feature['name'];
                    
                    $paramCode = $featureSettings['paramcode'] ?? ($featureId ?? '');
                    $valueCode = $featureSettings['valuecode'] ?? '';
                    
                    // Якщо немає valuecode в налаштуваннях, обчислюємо translit на льоту з першого значення
                    if (empty($valueCode) && !empty($feature['values'])) {
                        $firstValue = reset($feature['values']);
                        $valueCode = $this->translit->translitAlpha($firstValue);
                    }
                    
                    $escapedName = $this->xmlFeedHelper->escape($name);
                    $baseAttributes = ['name' => $escapedName];
                    if ($paramCode) {
                        $baseAttributes['paramcode'] = $this->xmlFeedHelper->escape($paramCode);
                    }
                    if ($valueCode) {
                        $baseAttributes['valuecode'] = $this->xmlFeedHelper->escape($valueCode);
                    }
                    if ($featureSettings && !empty($featureSettings['lang'])) {
                        $baseAttributes['lang'] = $this->xmlFeedHelper->escape($featureSettings['lang']);
                    }
                    
                    // Обробляємо значення
                    foreach ($feature['values'] as $value) {
                        $result[] = [
                            'tag' => 'param',
                            'data' => $this->xmlFeedHelper->escape($value),
                            'attributes' => $baseAttributes,
                        ];
                    }
                }
            }
        }

        // Додаємо обов'язкові параметри для всіх товарів
        // Одиниця виміру та кількість
        $result[] = [
            'tag' => 'param',
            'data' => 'шт.',
            'attributes' => [
                'paramcode' => 'measure',
                'name' => 'Одиниця виміру та кількість',
                'valuecode' => 'measure_pcs',
            ],
        ];

        // Мінімальна кратність товару
        $result[] = [
            'tag' => 'param',
            'data' => '1',
            'attributes' => [
                'paramcode' => 'ratio',
                'name' => 'Мінімальна кратність товару',
            ],
        ];

        // Штрих код (SKU)
        if (!empty($product->sku)) {
            $result[] = [
                'tag' => 'param',
                'data' => '<![CDATA[' . $product->sku . ']]>',
                'attributes' => [
                    'paramcode' => 'barcodes',
                    'name' => 'Штрих код',
                ],
            ];
        }

        //<width> та <height> - оптимізовано
        $widthFeatureId = $this->feed->settings['width_feature'] ?? null;
        if ($widthFeatureId && isset($product->features[$widthFeatureId])) {
            $widthValue = reset($product->features[$widthFeatureId]['values']);
            if ($widthValue) {
                $result['width']['data'] = $this->xmlFeedHelper->escape($widthValue);
            }
        }
        
        $heightFeatureId = $this->feed->settings['height_feature'] ?? null;
        if ($heightFeatureId && isset($product->features[$heightFeatureId])) {
            $heightValue = reset($product->features[$heightFeatureId]['values']);
            if ($heightValue) {
                $result['height']['data'] = $this->xmlFeedHelper->escape($heightValue);
            }
        }


        //<offer id="..."> - оптимізовано
        $item = [
            'tag' => 'offer',
            'attributes' => [
                'id' => $this->xmlFeedHelper->escape($product->sku),
                'available' => (($product->stock > 0 || $product->stock === null) ? 'true' : 'false'),
            ],
            'data' => $result
        ];

        if ($product->total_variants > 1) {
            $item['attributes']['group_id'] = $product->product_id;
        }

        return ExtenderFacade::execute(__METHOD__, [$item], func_get_args());
    }
}
