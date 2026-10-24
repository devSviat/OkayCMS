<?php


namespace Okay\Core\Routes\Strategies\Category;

use Okay\Core\EntityFactory;
use Okay\Core\Routes\Strategies\AbstractRouteStrategy;
use Okay\Core\ServiceLocator;
use Okay\Entities\CategoriesEntity;

class NoPrefixStrategy extends AbstractRouteStrategy
{
    /**
     * @var CategoriesEntity
     */
    private $categoriesEntity;

    private $mockRouteParams = ['/{$url}/?{$filtersUrl}', ['{$url}' => '', '{$filtersUrl}' => ''], ['{$url}' => '', '{$filtersUrl}' => '']];

    public function __construct()
    {
        $serviceLocator = ServiceLocator::getInstance();
        $entityFactory  = $serviceLocator->getService(EntityFactory::class);

        $this->categoriesEntity = $entityFactory->get(CategoriesEntity::class);
    }

    public function generateRouteParams($url)
    {
        $categoryUrl = $this->matchCategoryUrl($url);
        $category    = $this->categoriesEntity->get((string) $categoryUrl);

        if (empty($category)) {
            return $this->mockRouteParams;
        }

        $filter = trim($this->matchFiltersUrl($categoryUrl, $url), '/');
        
        return [
            '/{$url}/?{$filtersUrl}',
            [
                '{$url}' => "({$categoryUrl})",
                '{$filtersUrl}' => '(' . $filter . ')',
            ],
            []
        ];
    }

    private function matchCategoryUrl($url)
    {
        preg_match("~(?:category_features/)?([^/]+)~ui", $url, $matches);

        return $matches[1] ?? '';
    }

    private function matchFiltersUrl($categoryUrl, $url)
    {
        if (str_contains($url, 'category_features')) {
            $url = substr($url, strlen('category_features') + 1);
        }
        
        return substr($url, strlen($categoryUrl));
    }
}