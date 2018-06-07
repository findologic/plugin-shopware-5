<?php

namespace FinSearchAPI\Helper;

use Exception;
use FinSearchAPI\Bundles\FacetResult\ColorListItem;
use FinSearchAPI\Bundles\FacetResult\ColorPickerFacetResult;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\StoreFrontBundle;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Models\Media\Media;
use Shopware\Models\Search\CustomFacet;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\File\File;


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


		$xmlResponse = new SimpleXMLElement( $responseText );

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
			$container = Shopware()->Container();
			/** @var StoreFrontBundle\Service\ProductNumberServiceInterface $productService */
			$productService = $container->get( 'shopware_storefront.product_number_service' );
			/* READ PRODUCT IDS */
			foreach ( $xmlResponse->products->product as $product ) {
				try {

					$articleId = (string) $product->attributes()['id'];

					$productCheck = $productService->getMainProductNumberById( $articleId );

					if ( $articleId === '' || $articleId === null ) {
						continue;
					}
					/** @var array $baseArticle */
					$baseArticle                = array();
					$baseArticle['orderNumber'] = $productCheck;
					$baseArticle['detailId']    = self::getDetailIdForOrdernumber( $productCheck );
					$foundProducts[ $articleId ] = $baseArticle;
				} catch ( Exception $ex ) {
				//	die($ex->getMessage());
					// No Mapping for Search Results
					continue;
				}
			}
		} catch ( Exception $ex ) {
			// Logging Function
			//print_r($ex->getMessage());
		}

		return $foundProducts;
	}

	public static function getDetailIdForOrdernumber( $ordernumber ) {
		$db              = Shopware()->Container()->get( 'db' );
		$checkForArticle = $db->fetchRow( '
        SELECT id AS id FROM s_articles_details WHERE ordernumber=?
        ', [ $ordernumber ] );

		if ( isset( $checkForArticle['id'] ) ) {
			return $checkForArticle['id'];
		}

		return false;
	}


	/**
	 * @param array $foundProducts
	 *
	 * @return array
	 */
	public static function getShopwareArticlesFromFindologicId( array $foundProducts ) {
		/* PREPARE SHOPWARE ARRAY */
		$searchResult = array();
		foreach ( $foundProducts as $productKey => $sProduct ) {
			$searchResult[ $sProduct['orderNumber'] ] = new BaseProduct( $productKey, $sProduct['detailId'], $sProduct['orderNumber'] );
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
					$facets[] = self::createTreeviewFacet( $facetItem );
					break;
				case "label":
					switch ( $facetItem['select'] ) {
						case "single":
							// RadioFacetResult
							$facets[] = self::createRadioFacet( $facetItem );
							break;
						default:
							// ValueListFacetResult
							$facets[] = self::createValueListFacet( $facetItem );
							break;
					}
					break;
				case "color":
					$facets[] = self::createColorListFacet( $facetItem );
					break;
				case "image":
					$facets[] = self::createMediaListFacet( $facetItem );
					break;
				case "range-slider":
					$minValue = (float) $filter->attributes->selectedRange->min;
					$maxValue = (float) $filter->attributes->selectedRange->max;
					$facets[] = self::createRangeSlideFacet( $facetItem, $minValue, $maxValue );
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
			$facets[] = self::createFindologicFacet( (string) $filter->display, (string) $filter->name );
		}

		return $facets;
	}

	/**
	 * @param array $facetItem
	 *
	 * @return SearchBundle\FacetResult\MediaListFacetResult
	 */
	private static function createMediaListFacet( array $facetItem ) {
		$facetResult = new SearchBundle\FacetResult\MediaListFacetResult( $facetItem['name'], false, $facetItem['display'], self::prepareMediaItems( $facetItem['items'] ), $facetItem['name'] );

		return $facetResult;
	}

	/**
	 * @param array $facetItem
	 *
	 * @return SearchBundle\FacetResult\MediaListFacetResult
	 */
	private static function createColorListFacet( array $facetItem ) {
		$facetResult = new ColorPickerFacetResult( $facetItem['name'], false, $facetItem['display'], self::prepareColorItems( $facetItem['items'] ), $facetItem['name'] );

		return $facetResult;
	}

	/**
	 * @param array $facetItem
	 *
	 * @return SearchBundle\FacetResult\RadioFacetResult
	 */
	private static function createRadioFacet( array $facetItem ) {
		$facetResult = new SearchBundle\FacetResult\RadioFacetResult( $facetItem['name'], false, $facetItem['display'], self::prepareValueItems( $facetItem['items'] ), $facetItem['name'] );

		return $facetResult;
	}

	/**
	 * @param array $facetItem
	 *
	 * @return SearchBundle\FacetResult\ValueListFacetResult
	 */
	private static function createValueListFacet( array $facetItem ) {
		$facetResult = new SearchBundle\FacetResult\ValueListFacetResult( $facetItem['name'], false, $facetItem['display'], self::prepareValueItems( $facetItem['items'] ), $facetItem['name'] );

		return $facetResult;
	}

	/**
	 * @param SimpleXMLElement $facetItem
	 *
	 * @return TreeFacetResult
	 */
	private static function createTreeviewFacet( array $facetItem ) {
		$facetResult = new TreeFacetResult( $facetItem['name'],
			$facetItem['name'], false, $facetItem['display'], self::prepareTreeView( $facetItem['items'] ), $facetItem['name'] );

		return $facetResult;
	}

	/**
	 * @param SimpleXMLElement $facetItem
	 * @param Float $minValue
	 * @param Float $maxValue
	 *
	 * @return RangeFacetResult
	 */
	private static function createRangeSlideFacet( array $facetItem, Float $minValue, Float $maxValue ) {
		$facetResult = new RangeFacetResult( $facetItem['name'], $facetItem['name'], $facetItem['display'], $minValue, $maxValue, $minValue, $maxValue, 'min', 'max', $facetItem['name'] );

		return $facetResult;
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
		$currentFacet->setUniqueKey( $name );

		return $currentFacet;
	}

	/**
	 * @param $items
	 *
	 * @return array
	 */
	private static function prepareValueItems( $items ) {
		$response = array();
		foreach ( $items as $item ) {
			$valueListItem = new SearchBundle\FacetResult\ValueListItem( $item['name'], $item['name'], false );
			$response[]    = $valueListItem;
		}

		return $response;
	}

	/**
	 * @param $items
	 *
	 * @return array
	 */
	private static function prepareMediaItems( $items ) {
		$response = array();
		foreach ( $items as $item ) {
			if ( $item['image'] !== '' ) {
				$mediaItem = $item['image'];
			} else {
				$mediaItem = null;
			}
			try {
				$imageFile = new File( $mediaItem );
				if ( $imageFile->isFile() ) {
					$shopwareMedia = new Media();
					$shopwareMedia->setFile( $imageFile );
				}
			} catch ( Exception $fileNotFound ) {

			}

			$valueListItem = new SearchBundle\FacetResult\MediaListItem( $item['name'], $item['name'], false, $shopwareMedia );
			$response[]    = $valueListItem;
		}

		return $response;
	}

	/**
	 * @param $items
	 *
	 * @return array
	 */
	private static function prepareColorItems( $items ) {
		$response = array();
		foreach ( $items as $item ) {
			if ( $item['color'] !== '' ) {
				$mediaItem = $item['color'];
			}
			$valueListItem = new ColorListItem( $item['name'], $item['name'], false, null, $mediaItem );
			$response[]    = $valueListItem;
		}

		return $response;
	}

	/**
	 * @param $items
	 *
	 * @return array
	 */
	private static function createFilterItems( $items ) {
		$response = array();
		$tempItem = array();
		foreach ( $items as $subItem ) {
			$tempItem['name']  = (string) $subItem->name;
			$tempItem['image'] = ( $subItem->image !== null ? $subItem->image : '' );
			$tempItem['color'] = ( $subItem->color !== null ? $subItem->color : '' );
			if ( $subItem->items->item ) {
				$tempItem['items'] = self::createFilterItems( $subItem->items->item );
			}
			$response[] = $tempItem;
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
			$treeView   = new SearchBundle\FacetResult\TreeItem( $item['name'], $item['name'], false, self::prepareTreeView( $item['items'] ) );
			$response[] = $treeView;
		}

		return $response;
	}

	public static function checkDirectIntegration() {
		if ( ! isset( Shopware()->Session()->findologicApi ) ) {
			// LOAD STATUS
			$urlBuilder                          = new UrlBuilder();
			$status                              = $urlBuilder->getConfigStatus();
			Shopware()->Session()->findologicApi = ! $status;
		}

		return ! ( true == Shopware()->Session()->findologicApi );
	}

	public static function cleanString($string)
	{
		$string = str_replace("\\", '', addslashes(strip_tags($string)));
		$string = str_replace(array("\n", "\r", "\t"), ' ', $string);

		// Remove unprintable characters since they would cause an invalid XML.
		$string = preg_replace('/[[:^print:]]/', '', $string);

		return trim($string);
	}
}
