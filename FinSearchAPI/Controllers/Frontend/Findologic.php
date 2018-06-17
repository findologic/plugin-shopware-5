<?php


/**
 * Created by PhpStorm.
 * User: marcelwege
 * Date: 12.12.17
 * Time: 21:51
 */

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action {

    /**
     *
     */
    public function indexAction() {
        // INIT THE BL SYSTEM

        $shopKey = $this->request->get( 'shopkey' );
        $start = $this->request->get( 'start' );
        $length = $this->request->get( 'count' );

        /** @var \FinSearchAPI\ShopwareProcess $blController */
        $blController = $this->container->get( 'fin_search_api.shopware_process' );
        $blController->setShopKey( $shopKey );
        if ( $length !== null ) {
            $xmlDocument = $blController->getFindologicXml($start != null ? $start :  0, $length);
        } else {
            $xmlDocument = $blController->getFindologicXml();
        }

        $this->response->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
        $this->response->setBody($xmlDocument);
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender();
        return $this->response;
    }


}