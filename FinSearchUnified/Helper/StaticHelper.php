<?php

namespace FinSearchUnified\Helper;

use Exception;
use FinSearchUnified\Bundles\FacetResult\ColorListItem;
use FinSearchUnified\Bundles\FacetResult\ColorPickerFacetResult;
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
    public static function buildCategoryName($categoryId, $decode = true ) {
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
        $searchResult = [];
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
                    $facets[] = self::createColorListFacet( $facetItem );
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
    public static function getFindologicFacets( SimpleXMLElement $xmlResponse ) {
        $facets = [];

        foreach ( $xmlResponse->filters->filter as $filter ) {
            $facets[] = self::createFindologicFacet( (string) $filter->display, (string) $filter->name, (string) $filter->type, (string) $filter->select );
        }

        return $facets;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\MediaListFacetResult
     */
    private static function createMediaListFacet( array $facetItem ) {
        $enabled = false;
        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }
        $facetResult = new SearchBundle\FacetResult\MediaListFacetResult( $facetItem['name'], $enabled, $facetItem['display'], self::prepareMediaItems( $facetItem['items'], $facetItem['name'] ), $facetItem['name'] );

        return $facetResult;
    }

    /**
     * @param array $facetItem
     *
     * @return SearchBundle\FacetResult\MediaListFacetResult
     */
    private static function createColorListFacet( array $facetItem ) {
        $enabled = false;
        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }
        $facetResult = new ColorPickerFacetResult( $facetItem['name'], $enabled, $facetItem['display'], self::prepareColorItems( $facetItem['items'], $facetItem['name'] ), $facetItem['name'] );

        return $facetResult;
    }

    public static function arrayHasFacet( $facetArray, $facetName ) {
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
    private static function createRadioFacet( array $facetItem ) {
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
    private static function createValueListFacet( array $facetItem ) {
        $enabled = false;
        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }
        $facetResult = new SearchBundle\FacetResult\ValueListFacetResult( $facetItem['name'], $enabled, $facetItem['display'], self::prepareValueItems( $facetItem['items'], $facetItem['name'] ), $facetItem['name'], $facetItem['name'] );

        return $facetResult;
    }

    public static function createSelectedFacet( $name, $label, $itemValue ) {
        $facetResult = new SearchBundle\FacetResult\ValueListFacetResult( $name, true, $label, self::createSelectValues( $itemValue ), $name, $name );

        return $facetResult;
    }

    private static function createSelectValues($items ) {
        $results = [];
        foreach ( $items as $item ) {
            $results[] = new SearchBundle\FacetResult\ValueListItem( $item, $item, true );
        }

        return $results;
    }

    /**
     * @param SimpleXMLElement $facetItem
     *
     * @return TreeFacetResult
     */
    private static function createTreeviewFacet( array $facetItem ) {
        $enabled = false;
        if ( array_key_exists( $facetItem['name'], $_REQUEST ) ) {
            $enabled = true;
        }
        $facetResult = new TreeFacetResult( $facetItem['name'],
            $facetItem['name'], $enabled, $facetItem['display'], self::prepareTreeView( $facetItem['items'], $facetItem['name'] ), $facetItem['name'] );

        return $facetResult;
    }

    /**
     * @param SimpleXMLElement $facetItem
     * @param float $minValue
     * @param float $maxValue
     *
     * @return RangeFacetResult
     */
    private static function createRangeSlideFacet( array $facetItem, Float $minValue, Float $maxValue ) {
        $facetResult = new RangeFacetResult( $facetItem['name'], false, $facetItem['display'], $minValue, $maxValue, $minValue, $maxValue, 'min', 'max', $facetItem['name'] );

        return $facetResult;
    }

    /**
     * @param string $label
     * @param string $name
     *
     * @return CustomFacet
     */
    public static function createFindologicFacet($label, $name, $type, $filter ) {
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
     * @param $items
     *
     * @return array
     */
    private static function prepareValueItems( $items, $name ) {

        $response      = [];
        $selectedItems = explode( '|', $_REQUEST[ $name ] );
        foreach ( $selectedItems as $selected_item ) {
            if ( $selected_item === '' || $selected_item === null ) {
                continue;
            }
            $valueListItem = new SearchBundle\FacetResult\ValueListItem( $selected_item, $selected_item, true );
            $response[]    = $valueListItem;
        }
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
    private static function prepareMediaItems( $items, $name ) {
        $response = [];
        foreach ( $items as $item ) {
            $enabled = false;
            if ( array_key_exists( $name, $_REQUEST ) ) {
                $selectedItems = explode( '|', $_REQUEST[ $name ] );
                {
                    foreach ( $selectedItems as $selected_item ) {
                        if ( $selected_item == $item ) {
                            $enabled = true;
                        }
                    }
                }
            }
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

            $valueListItem = new SearchBundle\FacetResult\MediaListItem( $item['name'], $item['name'], $enabled, $shopwareMedia );
            $response[]    = $valueListItem;
        }

        return $response;
    }

    /**
     * @param $items
     *
     * @return array
     */
    private static function prepareColorItems( $items, $name ) {
        $response = [];
        foreach ( $items as $item ) {
            $enabled = false;
            if ( array_key_exists( $name, $_REQUEST ) ) {
                $selectedItems = explode( '|', $_REQUEST[ $name ] );
                {
                    foreach ( $selectedItems as $selected_item ) {
                        if ( $selected_item == $item ) {
                            $enabled = true;
                        }
                    }
                }
            }
            if ( $item['color'] !== '' ) {
                $mediaItem = $item['color'];
            }
            $valueListItem = new ColorListItem( $item['name'], $item['name'], $enabled, null, $mediaItem );
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
        $response = [];
        $tempItem = [];
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
    private static function prepareTreeView( $items, $name, $recurseName = null ) {

        $response = [];
        $selectedItems = explode( '|', $_REQUEST[ $name ] );
        foreach ( $selectedItems as $selected_item ) {
            if ( $selected_item === '' || $selected_item === null ) {
                continue;
            }
            $labelArray = explode('_', $selected_item);
            $labelString = $labelArray[count($labelArray)-1];
            $treeView   = new SearchBundle\FacetResult\TreeItem( $selected_item,$labelString,true,null );
            $response[] = $treeView;
        }
        foreach ( $items as $item ) {
            $treeName = $item['name'];
            if ($recurseName !== null){
                $treeName = $recurseName . '_' . $item['name'];
            }
            $treeView   = new SearchBundle\FacetResult\TreeItem( $treeName, $item['name'], false, self::prepareTreeView( $item['items'],null,$treeName ) );
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

    public static function cleanString( $string ) {
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
            self::checkDirectIntegration() ||
            !self::isFindologicActive() ||
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

    public static function getProductStreamKey()
    {
        return 'findologicProductStreams';
    }
}
