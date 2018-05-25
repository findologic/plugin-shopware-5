<?php

namespace findologicDI\Bundles;

use findologicDI\Helper\StaticHelper;
use findologicDI\Helper\UrlBuilder;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;

class FindologicFacetGateway implements CustomFacetGatewayInterface {


	private $originalService;

	private $urlBuilder;


	public function __construct( CustomFacetGatewayInterface $service ) {
		$this->originalService = $service;
		$this->urlBuilder      = new UrlBuilder();
	}

	/**
	 * @param int[] $ids
	 * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
	 *
	 * @return \Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet indexed by id
	 */
	public function getList( array $ids, \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context ) {
		// TODO: Implement getList() method.
		return $this->originalService->getList( $ids, $context );
	}

	/**
	 * @param array $categoryIds
	 * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
	 *
	 * @return array indexed by category id, each element contains a list of CustomFacet
	 */
	public function getFacetsOfCategories( array $categoryIds, \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context ) {
		if (!StaticHelper::checkDirectIntegration() || !(bool)Shopware()->Config()->get( 'ActivateFindologic' )){
			return $this->originalService->getFacetsOfCategories( $categoryIds, $context );
		}
		// Facets abfragen
		$categoryId = $categoryIds[0];
		$this->urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
		$response = $this->urlBuilder->buildCategoryUrlAndGetResponse($categoryId);
		if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200 ) {
			$xmlResponse = StaticHelper::getXmlFromResponse($response);
			$categoryFacets = array();
			$categoryFacets[$categoryId] = StaticHelper::getFindologicFacets($xmlResponse);
			return $categoryFacets;
		}
		return $this->originalService->getFacetsOfCategories( $categoryIds, $context );
	}

	/**
	 * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
	 *
	 * @return \Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet
	 */
	public function getAllCategoryFacets( \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context ) {
		// TODO: Implement getAllCategoryFacets() method.
		return $this->originalService->getAllCategoryFacets( $context );
	}
}