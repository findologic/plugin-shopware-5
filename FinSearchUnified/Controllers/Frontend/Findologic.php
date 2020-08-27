<?php

use FinSearchUnified\ShopwareProcess;

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    public function indexAction()
    {
        $shopKey = $this->request->get('shopkey');
        $start = (int)$this->request->getParam('start', 0);
        $count = (int)$this->request->get('count');
        $productId = $this->request->get('productId');
        $language = $this->request->get('language');

        /** @var ShopwareProcess $shopwareProcess */
        $shopwareProcess = $this->container->get('fin_search_unified.shopware_process');
        $shopwareProcess->setShopKey($shopKey);
        $shopwareProcess->setShopValues();

        if ($productId) {
            $xmlDocument = $shopwareProcess->getProductById($productId);
        } elseif ($count !== null) {
            $xmlDocument = $shopwareProcess->getFindologicXml($language, $start, $count);
        } else {
            $xmlDocument = $shopwareProcess->getFindologicXml($language);
        }

        $headerHandler = Shopware()->Container()->get('fin_search_unified.helper.header_handler');
        $headers = $headerHandler->getHeaders();

        foreach ($headers as $name => $value) {
            $this->response->setHeader($name, $value, true);
        }

        $this->response->setBody($xmlDocument);
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender();

        return $this->response;
    }
}
