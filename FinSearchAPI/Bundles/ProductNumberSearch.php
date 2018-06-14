<?php

namespace FinSearchAPI\Bundles;

use FinSearchAPI\Helper\FacetBuilder;
use FinSearchAPI\Helper\StaticHelper;
use FinSearchAPI\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;

class ProductNumberSearch implements \Shopware\Bundle\SearchBundle\ProductNumberSearchInterface {

	private $urlBuilder;

	private $facetBuilder;

	private $originalService;

	public function __construct( ProductNumberSearchInterface $service ) {
		$this->urlBuilder      = new UrlBuilder();
		$this->originalService = $service;
	}

	/**
	 * Creates a product search result for the passed criteria object.
	 * The criteria object contains different core conditions and plugin conditions.
	 * This conditions has to be handled over the different condition handlers.
	 *
	 * The search gateway has to implement an event which plugin can be listened to,
	 * to add their own handler classes.
	 *
	 * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
	 * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
	 *
	 * @return SearchBundle\ProductNumberSearchResult
	 */
	public function search( Criteria $criteria, \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context ) {
		if (StaticHelper::checkDirectIntegration() || !(bool)Shopware()->Config()->get( 'ActivateFindologic' )){
			return $this->originalService->search( $criteria, $context );
		}
		try {
			/* SEND REQUEST TO FINDOLOGIC */
			$this->urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
			$response  = $this->urlBuilder->buildQueryUrlAndGetResponse( $criteria );
			if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200 ) {

				$xmlResponse = StaticHelper::getXmlFromResponse($response);

				$foundProducts= StaticHelper::getProductsFromXml($xmlResponse);

				$facets = StaticHelper::getFacetResultsFromXml($xmlResponse);

				$searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
				setcookie('Fallback', 0);
				return new SearchBundle\ProductNumberSearchResult( $searchResult, count( $searchResult ), $facets );
			} else {
				setcookie('Fallback', 1);
				return $this->originalService->search( $criteria, $context );
			}


		} catch ( \Zend_Http_Client_Exception $e ) {
			return $this->originalService->search( $criteria, $context );
		}
	}




}