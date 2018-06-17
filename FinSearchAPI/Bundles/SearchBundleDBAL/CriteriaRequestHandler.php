<?php

namespace FinSearchAPI\Bundles\SearchBundleDBAL;

use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;

class CriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    /**
     * @param \Enlight_Controller_Request_RequestHttp                       $request
     * @param \Shopware\Bundle\SearchBundle\Criteria                        $criteria
     * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
     */
    public function handleRequest(
        \Enlight_Controller_Request_RequestHttp $request,
        \Shopware\Bundle\SearchBundle\Criteria $criteria,
        \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
    ) {

        // TODO: Implement handleRequest() method.
    }
}
