<?php

use FinSearchUnified\Constants;
use FinSearchUnified\ShopwareProcess;

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    public function indexAction()
    {
        $shopkey = $this->request->get('shopkey');
        $start = (int)$this->request->getParam('start', 0);
        $count = (int)$this->request->get('count', 20);
        $productId = $this->request->get('productId');

        /** @var ShopwareProcess $shopwareProcess */
        $shopwareProcess = $this->container->get('fin_search_unified.shopware_process');
        $shopwareProcess->setShopKey($shopkey);
        $shopwareProcess->setUpExportService();

        if ($productId) {
            $document = $shopwareProcess->getProductsById($productId);
        } else {
            $document = $shopwareProcess->getFindologicXml($start, $count);
        }

        $headerHandler = Shopware()->Container()->get('fin_search_unified.helper.header_handler');

        $exportErrors = $shopwareProcess->getExportService()->getErrors();
        if (count($exportErrors) > 0) {
            $headerHandler->setContentType(Constants::CONTENT_TYPE_JSON);
        } else {
            $headerHandler->setContentType(Constants::CONTENT_TYPE_XML);
        }

        $headers = $headerHandler->getHeaders();
        foreach ($headers as $name => $value) {
            $this->response->setHeader($name, $value, true);
        }

        $this->response->setBody($document);
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender();

        return $this->response;
    }
}
