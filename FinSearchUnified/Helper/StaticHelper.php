<?php

namespace FinSearchUnified\Helper;

use Exception;
use SimpleXMLElement;
use Zend_Http_Client;
use Zend_Http_Response;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\StoreFrontBundle;
use Shopware\Models\Search\CustomFacet;
use FinSearchUnified\Bundles\FacetResult as FinFacetResult;

class StaticHelper
{
    /**
     * @param int $categoryId
     * @param bool $decode
     *
     * @return string
     */
    public static function buildCategoryName($categoryId, $decode = true)
    {
        $categories    = Shopware()->Modules()->Categories()->sGetCategoriesByParent( $categoryId );
        $categoryNames = [];
        foreach ( $categories as $category ) {
            if ( $decode ) {
                $categoryNames[] = rawurlencode( $category['name'] );
            } else {
                $categoryNames[] = $category['name'];
            }
        }
        $categoryNames = array_reverse( $categoryNames );
        $categoryName  = implode( '_', $categoryNames );

        return $categoryName;
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     * @return null|string
     */
    public static function checkIfRedirect(SimpleXMLElement $xmlResponse)
    {
        /** @var SimpleXMLElement $landingpage */
        $landingpage = $xmlResponse->landingPage;
        if (isset($landingpage) && $landingpage != null && count($landingpage->attributes()) > 0){
            /** @var string $redirect */
            $redirect = (string)$landingpage->attributes()->link;
            return $redirect;
        }
        return null;
    }

    /**
     * @param Zend_Http_Response $response
     *
     * @return SimpleXMLElement
     */
    public static function getXmlFromResponse(Zend_Http_Response $response)
    {
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
    public static function getProductsFromXml(SimpleXMLElement $xmlResponse)
    {
        $foundProducts = [];

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
                    $baseArticle                 = [];
                    $baseArticle['orderNumber']  = $productCheck;
                    $baseArticle['detailId']     = self::getDetailIdForOrdernumber( $productCheck );
                    $foundProducts[ $articleId ] = $baseArticle;
                } catch ( Exception $ex ) {
                    // No Mapping for Search Results
                    continue;
                }
            }
        } catch ( Exception $ex ) {
            // Logging Function
        }

        return $foundProducts;
    }

    /**
     * @param string $ordernumber
     *
     * @return bool
     */
    public static function getDetailIdForOrdernumber($ordernumber)
    {
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
    public static function getShopwareArticlesFromFindologicId(array $foundProducts)
    {
        /* PREPARE SHOPWARE ARRAY */
        $searchResult = [];
        foreach ( $foundProducts as $productKey => $sProduct ) {
            $searchResult[ $sProduct['orderNumber'] ] = new StoreFrontBundle\Struct\BaseProduct(
                $productKey,
                $sProduct['detailId'],
                $sProduct['orderNumber']
            );
        }

        return $searchResult;
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     *
     * @return array
     */
    public static function getFacetResultsFromXml(SimpleXMLElement $xmlResponse)
    {
        /* FACETS */
        $facets = [];
        foreach ( $xmlResponse->filters->filter as $filter ) {
            $facetItem            = [];
            $facetItem['name']    = (string) $filter->name;
            $facetItem['select']  = (string) $filter->select;
            $facetItem['display'] = (string) $filter->display;
            $facetItem['type']    = (string) $filter->type;
            $facetItem['items']   = self::createFilterItems( $filter->items->item );

            switch ( $facetItem['type'] ) {
                case 'select':
                    $facets[] = self::createTreeviewFacet( $facetItem );
                    break;
                case 'label':
                    switch ( $facetItem['select'] ) {
                        case 'single':
                            // RadioFacetResult
                            $facets[] = self::createRadioFacet( $facetItem );
                            break;
                        default:
                            // ValueListFacetResult
                            $facets[] = self::createValueListFacet( $facetItem );
                            break;
                    }
                    break;
                case 'color':
                    if ($facetItem['items'] && $facetItem['items'][0]['image']) {
                        $facets[] = self::createMediaListFacet( $facetItem );
                    } else {
                        $facets[] = self::createColorListFacet( $facetItem );
                    }

                    break;
                case 'image':
                    $facets[] = self::createMediaListFacet( $facetItem );
                    break;
                case 'range-slider':
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
    public static function getFindologicFacets(SimpleXMLElement $xmlResponse)
    {
        $facets = [];

        foreach ( $xmlResponse->filters->filter as $filter ) {
            $facets[] = self::createFindologicFacet(
                (string) $filter->display,
                (string) $filter->name,
                (string) $filter->type,
                (string) $filter->select
            );
        }

        return $facets;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\MediaListFacetResult
     */
    private static function createMediaListFacet(array $facetItem)
    {
        $enabled = false;

        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }

        $facetResult = new SearchBundle\FacetResult\MediaListFacetResult(
            $facetItem['name'],
            $enabled,
            $facetItem['display'],
            self::prepareMediaItems($facetItem['items'], $facetItem['name']),
            $facetItem['name']
        );

        return $facetResult;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\MediaListFacetResult
     */
    private static function createColorListFacet(array $facetItem)
    {
        $enabled = false;

        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }

        $facetResult = new FinFacetResult\ColorPickerFacetResult(
            $facetItem['name'],
            $enabled,
            $facetItem['display'],
            self::prepareColorItems( $facetItem['items'], $facetItem['name'] ),
            $facetItem['name']
        );

        return $facetResult;
    }

    /**
     * @param SearchBundle\FacetResultInterface[] $facetArray
     * @param string $facetName
     *
     * @return bool|SearchBundle\FacetResultInterface
     */
    public static function arrayHasFacet($facetArray, $facetName)
    {
        /** @var SearchBundle\FacetResultInterface $facet */
        foreach ( $facetArray as $facet ) {
            if ( $facet->getFacetName() === $facetName ) {
                return $facet;
            }
        }

        return false;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\RadioFacetResult
     */
    private static function createRadioFacet(array $facetItem)
    {
        $enabled = false;
        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }
        $facetResult = new SearchBundle\FacetResult\RadioFacetResult( $facetItem['name'], $enabled, $facetItem['display'], self::prepareValueItems( $facetItem['items'], $facetItem['name'] ), $facetItem['name'] );

        return $facetResult;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\ValueListFacetResult
     */
    private static function createValueListFacet(array $facetItem)
    {
        $enabled = false;

        if (array_key_exists($facetItem['name'], $_REQUEST)) {
            $enabled = true;
        }

        $facetResult = new SearchBundle\FacetResult\ValueListFacetResult(
            $facetItem['name'],
            $enabled,
            $facetItem['display'],
            self::prepareValueItems($facetItem['items'], $facetItem['name']),
            $facetItem['name'],
            $facetItem['name']
        );

        return $facetResult;
    }

    /**
     * @param string $name
     * @param string $label
     * @param array $itemValue
     *
     * @return SearchBundle\FacetResult\ValueListFacetResult
     */
    public static function createSelectedFacet($name, $label, $itemValue)
    {
        $facetResult = new SearchBundle\FacetResult\ValueListFacetResult(
            $name,
            true,
            $label,
            self::createSelectValues($itemValue),
            $name,
            $name
        );

        return $facetResult;
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private static function createSelectValues($items)
    {
        $results = [];
        foreach ( $items as $item ) {
            $results[] = new SearchBundle\FacetResult\ValueListItem( $item, $item, true );
        }

        return $results;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\TreeFacetResult
     */
    private static function createTreeviewFacet(array $facetItem)
    {
        $enabled = false;

        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }

        $facetResult = new SearchBundle\FacetResult\TreeFacetResult(
            $facetItem['name'],
            $facetItem['name'],
            $enabled,
            $facetItem['display'],
            self::prepareTreeView($facetItem['items'], $facetItem['name']),
            $facetItem['name']
        );

        return $facetResult;
    }

    /**
     * @param array $facetItem
     * @param float $minValue
     * @param float $maxValue
     *
     * @return SearchBundle\FacetResult\RangeFacetResult
     */
    private static function createRangeSlideFacet(array $facetItem, $minValue, $maxValue)
    {
        $facetResult = new SearchBundle\FacetResult\RangeFacetResult(
            $facetItem['name'],
            false,
            $facetItem['display'],
            $minValue,
            $maxValue,
            $minValue,
            $maxValue,
            'min',
            'max',
            $facetItem['name']
        );

        return $facetResult;
    }

    /**
     * @param string $label
     * @param string $name
     * @param string $type
     * @param string $filter
     *
     * @return CustomFacet
     */
    public static function createFindologicFacet($label, $name, $type, $filter)
    {
        $currentFacet = new CustomFacet();
        $currentFacet->setName( $name );
        $currentFacet->setUniqueKey( $name );

        switch ( $type ) {
            case 'select':
                $facetType = new SearchBundle\Facet\ProductAttributeFacet( $name, SearchBundle\Facet\ProductAttributeFacet::MODE_VALUE_LIST_RESULT, $name, $label );
                break;
            case 'range-slider':
                $facetType = new SearchBundle\Facet\ProductAttributeFacet( $name, SearchBundle\Facet\ProductAttributeFacet::MODE_RANGE_RESULT, $name, $label );
                break;
            case 'label':
                if ( $filter == 'single' ) {
                    $facetType = new SearchBundle\Facet\ProductAttributeFacet( $name, SearchBundle\Facet\ProductAttributeFacet::MODE_RADIO_LIST_RESULT, $name, $label );
                } else {
                    $facetType = new SearchBundle\Facet\ProductAttributeFacet( $name, SearchBundle\Facet\ProductAttributeFacet::MODE_VALUE_LIST_RESULT, $name, $label );
                }
                break;
            default:
                $facetType = new SearchBundle\Facet\ProductAttributeFacet( $name, SearchBundle\Facet\ProductAttributeFacet::MODE_VALUE_LIST_RESULT, $name, $label );
                break;
        }

        $currentFacet->setFacet( $facetType );

        return $currentFacet;
    }

    /**
     * @param array $items
     * @param string $name
     *
     * @return array
     */
    private static function prepareValueItems($items, $name)
    {
        $response      = [];
        $selectedItems = explode( '|', $_REQUEST[ $name ] );

        foreach ( $items as $item ) {
            if (in_array($item['name'], $selectedItems)) {
                $enabled = true;
            } else {
                $enabled = false;
            }

            $valueListItem = new SearchBundle\FacetResult\ValueListItem( $item['name'], $item['name'], $enabled );
            $response[]    = $valueListItem;
        }

        return $response;
    }

    /**
     * @param array $items
     * @param string $name
     *
     * @return array
     */
    private static function prepareMediaItems($items, $name)
    {
        $values = [];
        $selectedItems = explode('|', Shopware()->Front()->Request()->getParam($name, []));

        $httpClient = new Zend_Http_Client();
        $httpClient->setMethod(Zend_Http_Client::HEAD);

        foreach ($items as $item) {
            $media = null;
            $active = in_array($item['name'], $selectedItems);

            try {
                $httpClient->setUri($item['image']);
                $response = $httpClient->request();
            } catch (Exception $e) {
                $response = null;
            }

            // Explicitly use Zend_Http_Response::isError here since only status codes >= 400 should count as errors.
            if ($response === null || $response->isError()) {
                $media = null;
            } else {
                $media = new StoreFrontBundle\Struct\Media();
                $media->setFile($item['image']);
            }

            $values[] = new SearchBundle\FacetResult\MediaListItem($item['name'], $item['name'], $active, $media);
        }

        return $values;
    }

    /**
     * @param array $items
     * @param string $name
     *
     * @return array
     */
    private static function prepareColorItems($items, $name)
    {
        $values = [];
        $selectedItems = explode('|', Shopware()->Front()->Request()->getParam($name, []));

        foreach ($items as $item) {
            $active = in_array($item['name'], $selectedItems);
            $color = $item['color'] ?: null;

            $values[] = new FinFacetResult\ColorListItem($item['name'], $item['name'], $active, $color);
        }

        return $values;
    }

    /**
     * @param $items
     *
     * @return array
     */
    private static function createFilterItems( $items )
    {
        $response = [];
        $tempItem = [];
        foreach ( $items as $subItem ) {
            $tempItem['name']  = (string) $subItem->name;
            $tempItem['image'] = (string) ( $subItem->image !== null ? $subItem->image[0] : '' );
            $tempItem['color'] = (string) ( $subItem->color !== null ? $subItem->color[0] : '' );
            if ( $subItem->items->item ) {
                $tempItem['items'] = self::createFilterItems( $subItem->items->item );
            }
            $response[] = $tempItem;
        }

        return $response;
    }

    /**
     * @param array $items
     * @param string|null $name
     * @param string|null $recurseName
     *
     * @return array
     */
    private static function prepareTreeView($items, $name, $recurseName = null)
    {
        $response = [];
        $selectedItems = explode('|', $_REQUEST[$name]);

        foreach ( $items as $item ) {
            $treeName = $item['name'];

            if ($recurseName !== null) {
                $treeName = $recurseName . '_' . $item['name'];
            }

            if (in_array($treeName, $selectedItems)) {
                $enabled = true;
            } else {
                $enabled = false;
            }

            $treeView = new SearchBundle\FacetResult\TreeItem(
                $treeName,
                $item['name'],
                $enabled,
                self::prepareTreeView($item['items'], $name, $treeName)
            );
            $response[] = $treeView;
        }

        return $response;
    }

    public static function checkDirectIntegration()
    {
        if ( ! isset( Shopware()->Session()->findologicApi ) ) {
            // LOAD STATUS
            $urlBuilder                          = new UrlBuilder();
            $status                              = $urlBuilder->getConfigStatus();
            Shopware()->Session()->findologicApi = ! $status;
        }

        return ! ( true == Shopware()->Session()->findologicApi );
    }

    public static function cleanString( $string )
    {
        $string = str_replace( '\\', '', addslashes( strip_tags( $string ) ) );
        $string = str_replace( [ "\n", "\r", "\t" ], ' ', $string );

        // Remove unprintable characters since they would cause an invalid XML.
        $string = preg_replace( '/[[:^print:]]/', '', $string );

        return trim( $string );
    }

    /**
     * Checks if the FINDOLOGIC search should actually be performed.
     *
     * @return bool
     */
    public static function useShopSearch()
    {
        return (
            !self::isFindologicActive() ||
            self::checkDirectIntegration() ||
            (
                !Shopware()->Session()->offsetGet('isCategoryPage') &&
                !Shopware()->Session()->offsetGet('isSearchPage')
            ) ||
            (
                Shopware()->Session()->offsetGet('isCategoryPage') &&
                !(bool) Shopware()->Config()->get('ActivateFindologicForCategoryPages')
            )
        );
    }

    /**
     * Checks if FINDOLOGIC search has been activated properly
     *
     * @return bool
     */
    public static function isFindologicActive()
    {
        return (bool) Shopware()->Config()->get('ActivateFindologic') &&
            !empty(trim(Shopware()->Config()->get('ShopKey'))) &&
            Shopware()->Config()->get('ShopKey') !== 'Findologic Shopkey';
    }

    /**
     * @param string $shopkey
     * @param string $usergroup
     * @return string
     */
    public static function calculateUsergroupHash($shopkey, $usergroup)
    {
        $hash = base64_encode($shopkey ^ $usergroup);

        return $hash;
    }

    /**
     * @param string $shopkey
     * @param string $hash
     * @return int
     */
    public static function decryptUsergroupHash($shopkey, $hash)
    {
        return $shopkey ^ base64_decode($hash);
    }

    /**
     * Checks if $haystack ends with $needle.
     *
     * @param string $needle
     * @param string $haystack
     * @return bool
     */
    public static function stringEndsWith($needle, $haystack)
    {
        $start = -1 * strlen($needle);

        return substr($haystack, $start) === $needle;
    }
}
