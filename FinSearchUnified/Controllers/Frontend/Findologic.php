<?php

use FinSearchUnified\Constants;
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
        $shopwareProcess->setUpExportService();

        if ($productId) {
            $document = $shopwareProcess->getProductById($productId);
        } elseif ($count !== null) {
            $document = $shopwareProcess->getFindologicXml($language, $start, $count);
        } else {
            $document = $shopwareProcess->getFindologicXml($language);
        }

        $headerHandler = Shopware()->Container()->get('fin_search_unified.helper.header_handler');

        $exportErrors = $shopwareProcess->exportService->getErrors();
        if (count($exportErrors)) {
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
