<?php


/**
 * Created by PhpStorm.
 * User: marcelwege
 * Date: 12.12.17
 * Time: 21:51
 */

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{

    /**
     *
     */
    public function indexAction(){
        // INIT THE BL SYSTEM

        $shopKey = $this->request->get('shopkey');

        /** @var \findologicDI\ShopwareProcess $blController */
        $blController = $this->container->get('findologic_d_i.shopware_process');
        $blController->setShopKey($shopKey);
        $xmlDocument = $blController->getFindologicXml();
        header('Content-Type: application/xml; charset=utf-8');
        die($xmlDocument);

    }

}