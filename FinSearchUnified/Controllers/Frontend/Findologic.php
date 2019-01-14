<?php

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{
    public function indexAction()
    {
        // INIT THE BL SYSTEM

        $shopKey = $this->request->get('shopkey');
        $start = (int)$this->request->getParam('start', 0);
        $count = $this->request->get('count');

        $exporter = $this->container->get('fin_search_unified.business_logic.export');

        $data = $exporter->getXml($shopKey, $start, $count);

        $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender();

        $this->response->setBody($data);

        return $this->response;
    }
}
