<?php

use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class SdymCriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    public function handleRequest(
        Enlight_Controller_Request_RequestHttp $request, Criteria $criteria, ShopContextInterface $context)
    {
        $param = $request->getParam(Constants::SDYM_PARAM_FORCE_QUERY);
        if ($param){
            $criteria->addCondition(new SimpleCondition(Constants::SDYM_PARAM_FORCE_QUERY));
        }
    }
}