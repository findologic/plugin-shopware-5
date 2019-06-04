<?php

namespace FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler;

use Enlight_Controller_Request_RequestHttp;
use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class SdymCriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    public function handleRequest(
        Enlight_Controller_Request_RequestHttp $request,
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        if ($request->getParam(Constants::SDYM_PARAM_FORCE_QUERY)) {
            $criteria->addCondition(new SimpleCondition(Constants::SDYM_PARAM_FORCE_QUERY));
        }
    }
}
