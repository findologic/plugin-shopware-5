<?php

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    public function indexAction()
    {
        // INIT THE BL SYSTEM

        $shopKey = $this->request->get('shopkey');
        $start = (int)$this->request->getParam('start', 0);
        $count = (int)$this->request->get('count');
        $language = $this->request->get('language');

        /** @var \FinSearchUnified\ShopwareProcess $blController */
        $blController = $this->container->get('fin_search_unified.shopware_process');
        $blController->setShopKey($shopKey);
        if ($count !== null) {
            $xmlDocument = $blController->getFindologicXml($language, $start, $count);
        } else {
            $xmlDocument = $blController->getFindologicXml($language);
        }

        $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
        $this->response->setBody($xmlDocument);
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender();

        return $this->response;
    }
}
