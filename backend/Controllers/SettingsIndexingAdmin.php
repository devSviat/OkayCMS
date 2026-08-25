<?php


namespace Okay\Admin\Controllers;


use Giggsey\Locale\Locale;
use libphonenumber\PhoneNumberUtil;
use Okay\Admin\Helpers\BackendSettingsHelper;
use Okay\Core\BackendTranslations;
use Okay\Core\Phone;

class SettingsIndexingAdmin extends IndexAdmin
{
    public function fetch()
    {
        if ($this->request->method('post')) {

            // Пишемо, лише коли поле справді прийшло. Нуль тут - легальний
            // вибір «не показувати», тож відсутнє поле не можна відрізнити від
            // нього за значенням: будь-який POST без нього тихо вимкнув би
            // page-all. Дефолт у post() теж не годиться - він підставляється
            // через empty() і зʼїв би саме нуль.
            $pageAllMaxItems = $this->request->post('catalog_page_all_max_items');
            if ($pageAllMaxItems !== null) {
                $pageAllMaxItems = (int)$pageAllMaxItems;
                if (!in_array($pageAllMaxItems, PAGE_ALL_ALLOWED_ITEMS, true)) {
                    $pageAllMaxItems = PAGE_ALL_MAX_ITEMS;
                }
                $this->settings->set('catalog_page_all_max_items', $pageAllMaxItems);
            }

            $canonicalCatalogPagination = (int)$this->request->post(
                'canonical_catalog_pagination',
                null,
                CANONICAL_FIRST_PAGE
            );

            // Значення з цього ж POST, а не перечитане з налаштувань: інакше
            // виправлення нижче мовчки залежало б від того, що set() уже
            // відпрацював вище.
            $pageAllIsOff = okay_page_all_max_items(
                $pageAllMaxItems ?? $this->settings->get('catalog_page_all_max_items')
            ) === PAGE_ALL_OFF;

            // При вимкненому page-all його адреса віддає першу сторінку. Канонікал
            // на неї склеїв би сторінки 2..N із першою, а самопосилання зробило б
            // із дубля окрему індексовану адресу. Комбінація виправляється при
            // збереженні, тож у списку одразу видно результат.
            if ($canonicalCatalogPagination === CANONICAL_PAGE_ALL && $pageAllIsOff) {
                $canonicalCatalogPagination = CANONICAL_FIRST_PAGE;
            }

            $canonicalCatalogPageAll = (int)$this->request->post(
                'canonical_catalog_page_all',
                null,
                CANONICAL_FIRST_PAGE
            );

            if ($pageAllIsOff) {
                $canonicalCatalogPageAll = CANONICAL_FIRST_PAGE;
            }

            $this->settings->set('canonical_catalog_pagination', $canonicalCatalogPagination);
            $this->settings->set('canonical_catalog_page_all', $canonicalCatalogPageAll);
            $this->settings->set('canonical_category_brand', 
                $this->request->post('canonical_category_brand', null, CANONICAL_WITHOUT_FILTER)
            );
            $this->settings->set('canonical_category_features', 
                $this->request->post('canonical_category_features', null, CANONICAL_WITHOUT_FILTER)
            );
            $this->settings->set('canonical_catalog_other_filter', 
                $this->request->post('canonical_catalog_other_filter', null, CANONICAL_WITHOUT_FILTER)
            );
            $this->settings->set('canonical_catalog_filter_pagination', 
                $this->request->post('canonical_catalog_filter_pagination', null, CANONICAL_WITHOUT_FILTER_FIRST_PAGE)
            );
            
            $this->settings->set('robots_catalog_pagination', 
                $this->request->post('robots_catalog_pagination', null, ROBOTS_INDEX_FOLLOW)
            );
            $this->settings->set('robots_catalog_page_all', 
                $this->request->post('robots_catalog_page_all', null, ROBOTS_INDEX_FOLLOW)
            );
            $this->settings->set('robots_category_brand', 
                $this->request->post('robots_category_brand', null, ROBOTS_INDEX_FOLLOW)
            );
            $this->settings->set('robots_category_features', 
                $this->request->post('robots_category_features', null, ROBOTS_INDEX_FOLLOW)
            );
            $this->settings->set('robots_catalog_other_filter', 
                $this->request->post('robots_catalog_other_filter', null, ROBOTS_INDEX_FOLLOW)
            );
            $this->settings->set('robots_catalog_filter_pagination', 
                $this->request->post('robots_catalog_filter_pagination', null, ROBOTS_INDEX_FOLLOW)
            );

            $this->settings->set('max_brands_filter_depth', $this->request->post('max_brands_filter_depth', 'integer', 0));
            $this->settings->set('max_other_filter_depth', $this->request->post('max_other_filter_depth', 'integer', 0));
            $this->settings->set('max_features_filter_depth', $this->request->post('max_features_filter_depth', 'integer', 0));
            $this->settings->set('max_features_values_filter_depth', $this->request->post('max_features_values_filter_depth', 'integer', 0));
            $this->settings->set('max_filter_depth', $this->request->post('max_filter_depth', 'integer', 0));
            
            $this->design->assign('message_success', 'saved');
        }
        
        // Готове значення, а не сире: інакше шаблон мусив би сам вирішувати,
        // що робити з незаданим налаштуванням.
        $this->design->assign(
            'catalog_page_all_max_items',
            okay_page_all_max_items($this->settings->get('catalog_page_all_max_items'))
        );

        $this->response->setContent('settings_indexing.tpl');
    }
}