<?php

namespace FinSearchAPI\Bundles\SearchBundleDBAL;

use Enlight_Controller_Request_RequestHttp as Request;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CriteriaRequestHandler implements CriteriaRequestHandlerInterface {

	/**
	 * @param \Enlight_Controller_Request_RequestHttp $request
	 * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
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