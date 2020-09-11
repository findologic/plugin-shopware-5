<?php

use FinSearchUnified\Constants;
use FinSearchUnified\ShopwareProcess;

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    const DEFAULT_START = 0;
    const DEFAULT_COUNT = 20;

    public function indexAction()
    {
        $shopkey = $this->request->get('shopkey');
        $start = (int)$this->request->getParam('start', self::DEFAULT_START);
        $count = (int)$this->request->get('count', self::DEFAULT_COUNT);
        $productId = $this->request->get('productId');

        /** @var ShopwareProcess $shopwareProcess */
        $shopwareProcess = $this->container->get('fin_search_unified.shopware_process');
        $shopwareProcess->setShopKey($shopkey);
        $shopwareProcess->setUpExportService();

        $headerHandler = Shopware()->Container()->get('fin_search_unified.helper.header_handler');
        $headerHandler->setContentType(Constants::CONTENT_TYPE_XML);

        if ($productId) {
            $document = $shopwareProcess->getProductsById($productId);

            if ($shopwareProcess->getExportService()->hasErrors()) {
                $headerHandler->setContentType(Constants::CONTENT_TYPE_JSON);
            }
        } else {
            $document = $shopwareProcess->getFindologicXml($start, $count);
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
