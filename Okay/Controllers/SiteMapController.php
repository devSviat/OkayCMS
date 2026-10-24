<?php


namespace Okay\Controllers;


use Okay\Helpers\SiteMapHelper;

class SiteMapController extends AbstractController
{
    
    public function renderXml(SiteMapHelper $siteMapHelper)
    {

        chdir(dirname(__DIR__, 2));

        /*
         * параметры с крона в виде key=val
         * доступные пары:
         * root_url=http://domain.com
         *
         * Файли пишуться лише з CLI: браузеру sitemap завжди віддається відповіддю.
         */

        $siteMapHelper->writeHead();

        $siteMapHelper->writePagesProcedure();
        $siteMapHelper->writeBlogProcedure();
        $siteMapHelper->writeCategoriesProcedure();
        $siteMapHelper->writeBrandsProcedure();
        $siteMapHelper->writeCategoryBrandsProcedure();
        $siteMapHelper->writeCustomProcedure();
        $siteMapHelper->writeProductsProcedure();

        $siteMapHelper->writeFooter();
    }
}
