<?php
/**
 * Created by PhpStorm.
 * User: wege
 * Date: 06.05.2018
 * Time: 09:28
 */

namespace findologicDI\Helper;

use Exception;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Models\Search\CustomFacet;
use SimpleXMLElement;


class StaticHelper {

	/**
	 * @param int $categoryId
	 *
	 * @return string
	 */
	public static function buildCategoryName( int $categoryId ) {
		$categories    = Shopware()->Modules()->Categories()->sGetCategoriesByParent( $categoryId );
		$categoryNames = [];
		foreach ( $categories as $category ) {
			$categoryNames[] = rawurlencode( $category['name'] );
		}
		$categoryNames = array_reverse( $categoryNames );
		$categoryName  = implode( '_', $categoryNames );

		return $categoryName;
	}

	/**
	 * @param \Zend_Http_Response $response
	 *
	 * @return SimpleXMLElement
	 */
	public static function getXmlFromResponse( \Zend_Http_Response $response ) {
		/* TLOAD XML RESPONSE */
		$responseText = (string) $response->getBody();
		$xmlResponse  = new SimpleXMLElement( $responseText );

		return $xmlResponse;
	}

	/**
	 * @param SimpleXMLElement $xmlResponse
	 *
	 * @return array
	 */
	public static function getProductsFromXml( SimpleXMLElement $xmlResponse ) {
		$foundProducts = array();
		try {
			/* READ PRODUCT IDS */
			foreach ( $xmlResponse->products->product as $product ) {
				try {
					$articleId = (string) $product->attributes();
					/** @var array $baseArticle */
					$baseArticle = Shopware()->Modules()->Articles()->sGetArticleById( $articleId );
					if ( $baseArticle != null and count( $baseArticle ) > 0 ) {
						array_push( $foundProducts, $baseArticle );
					}
				} catch ( Exception $ex ) {
					// No Mapping for Search Results
				}
			}
		} catch ( Exception $ex ) {
			// Logging Function
		}

		return $foundProducts;
	}

	/**
	 * @param array $foundProducts
	 *
	 * @return array
	 */
	public static function getShopwareArticlesFromFindologicId( array $foundProducts ) {
		/* PREPARE SHOPWARE ARRAY */
		$searchResult = array();
		foreach ( $foundProducts as $sProduct ) {
			$searchResult[ $sProduct['ordernumber'] ] = new BaseProduct( $sProduct['articleID'], $sProduct['articleDetailsID'], $sProduct['ordernumber'] );
		}

		return $searchResult;
	}

	/**
	 * @param SimpleXMLElement $xmlResponse
	 *
	 * @return array
	 */
	public static function getFacetResultsFromXml( $xmlResponse ) {
		/* FACETSS */
		$facets = array();
		foreach ( $xmlResponse->filters->filter as $filter ) {
			$facetItem            = array();
			$facetItem['name']    = (string) $filter->name;
			$facetItem['select']  = (string) $filter->select;
			$facetItem['display'] = (string) $filter->display;
			$facetItem['type']    = (string) $filter->type;
			$facetItem['items']   = self::createFilterItems( $filter->items->item );

			switch ( $facetItem['type'] ) {
				case "select":
					$facetResult = new TreeFacetResult( $facetItem['name'],
						$facetItem['name'], false, $facetItem['display'], self::prepareTreeView( $facetItem['items'] ), $facetItem['name'] );
					array_push( $facets, $facetResult );
					break;
				/*case "label":
					$facetResult = new TreeFacetResult( $facetItem['name'],
						$facetItem['display'], false, $facetItem['display'], $this->prepareTreeView( $facetItem['items'] ) );
					array_push( $facets, $facetResult );
					break;*/
				case "range-slider":
					$minValue    = (float) $filter->attributes->selectedRange->min;
					$maxValue    = (float) $filter->attributes->selectedRange->max;
					$facetResult = new RangeFacetResult( $facetItem['name'], $facetItem['name'], $facetItem['display'], $minValue, $maxValue, $minValue, $maxValue, 'min', 'max', $facetItem['name'] );
					array_push( $facets, $facetResult );
					break;
				default:
					break;
			}
		}

		return $facets;
	}

	/**
	 * @param SimpleXMLElement $xmlResponse
	 *
	 * @return array
	 */
	public static function getFindologicFacets( SimpleXMLElement $xmlResponse ) {
		$facets = array();
		foreach ( $xmlResponse->filters->filter as $filter ) {
			array_push( $facets, self::createFindologicFacet( (string) $filter->display, (string) $filter->name ) );
		}

		return $facets;
	}

	/**
	 * @param string $label
	 * @param string $name
	 *
	 * @return CustomFacet
	 */
	public static function createFindologicFacet( string $label, string $name ) {
		$currentFacet = new CustomFacet();
		$currentFacet->setName( $name );
		$currentFacet->setUniqueKey($name);

		return $currentFacet;
	}

	/**
	 * @param $item
	 *
	 * @return array
	 */
	private static function createFilterItems( $item ) {
		$response = array();
		$tempItem = array();
		foreach ( $item as $subItem ) {
			$tempItem['name'] = (string) $subItem->name;
			if ( $subItem->items->item ) {
				$tempItem['items'] = self::createFilterItems( $subItem->items->item );
			}
			array_push( $response, $tempItem );
		}

		return $response;
	}

	/**
	 * @param $items
	 *
	 * @return array
	 */
	private static function prepareTreeView( $items ) {
		$response = array();
		foreach ( $items as $item ) {
			$treeView = new SearchBundle\FacetResult\TreeItem( $item['name'], $item['name'], false, self::prepareTreeView( $item['items'] ) );
			array_push( $response, $treeView );
		}

		return $response;
	}

	public static function checkDirectIntegration(){
		if (!isset(Shopware()->Session()->findologicApi)){
			// LOAD STATUS
			$urlBuilder = new UrlBuilder();
			$status = $urlBuilder->getConfigStatus();
			Shopware()->Session()->findologicApi = $status;
		}

		if (Shopware()->Session()->findologicApi == false){
			return false;
		}
		return true;
	}

}