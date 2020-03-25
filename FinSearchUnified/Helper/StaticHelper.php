<?php

namespace FinSearchUnified\Helper;

use Enlight_View_Default;
use Exception;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Components\Environment;
use FinSearchUnified\Constants;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Bundle\StoreFrontBundle;
use SimpleXMLElement;
use Zend_Cache_Exception;

class StaticHelper
{
    /**
     * @param int $categoryId
     * @param bool $encode
     *
     * @return string
     */
    public static function buildCategoryName($categoryId, $encode = true)
    {
        $categories = Shopware()->Modules()->Categories()->sGetCategoriesByParent($categoryId);
        $categoryNames = [];
        foreach ($categories as $category) {
            if ($encode) {
                $categoryNames[] = rawurlencode(trim($category['name']));
            } else {
                $categoryNames[] = trim($category['name']);
            }
        }
        $categoryNames = array_reverse($categoryNames);
        $categoryName = implode('_', $categoryNames);

        return $categoryName;
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     *
     * @return null|string
     */
    public static function checkIfRedirect(SimpleXMLElement $xmlResponse)
    {
        /** @var SimpleXMLElement $landingpage */
        $landingpage = $xmlResponse->landingPage;
        if (isset($landingpage) && $landingpage !== null && count($landingpage->attributes()) > 0) {
            /** @var string $redirect */
            $redirect = (string)$landingpage->attributes()->link;

            return $redirect;
        }

        return null;
    }

    /**
     * @param string $responseText
     *
     * @return SimpleXMLElement
     */
    public static function getXmlFromResponse($responseText)
    {
        return new SimpleXMLElement($responseText);
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
            $productService = $container->get('shopware_storefront.product_number_service');
            /* READ PRODUCT IDS */
            foreach ($xmlResponse->products->product as $product) {
                try {
                    $articleId = (string)$product->attributes()['id'];

                    $productCheck = $productService->getMainProductNumberById($articleId);

                    if ($articleId === '' || $articleId === null) {
                        continue;
                    }
                    /** @var array $baseArticle */
                    $baseArticle = [];
                    $baseArticle['orderNumber'] = $productCheck;
                    $baseArticle['detailId'] = static::getDetailIdForOrdernumber($productCheck);
                    $foundProducts[$articleId] = $baseArticle;
                } catch (Exception $ex) {
                    // No Mapping for Search Results
                    continue;
                }
            }
        } catch (Exception $ex) {
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
        $db = Shopware()->Container()->get('db');
        $checkForArticle = $db->fetchRow('SELECT id AS id FROM s_articles_details WHERE ordernumber=?', [$ordernumber]);

        if (isset($checkForArticle['id'])) {
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
        foreach ($foundProducts as $productKey => $sProduct) {
            $searchResult[$sProduct['orderNumber']] = new StoreFrontBundle\Struct\BaseProduct(
                $productKey,
                $sProduct['detailId'],
                $sProduct['orderNumber']
            );
        }

        return $searchResult;
    }

    public static function cleanString($string)
    {
        $string = str_replace('\\', '', addslashes(strip_tags($string)));
        $string = str_replace(["\n", "\r", "\t"], ' ', $string);

        // Remove unprintable characters since they would cause an invalid XML.
        $string = static::removeControlCharacters($string);

        return trim($string);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isEmpty($value)
    {
        if (is_numeric($value) || is_object($value)) {
            return false;
        }

        if (empty($value) || (is_array($value) && empty(array_filter($value))) ||
            (!is_array($value) && empty(trim($value)))) {
            return true;
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public static function removeControlCharacters($value)
    {
        $result = preg_replace('/[\x{0000}-\x{001F}]|[\x{007F}]|[\x{0080}-\x{009F}]/u', '', $value);

        return $result === null ? $value : $result;
    }

    /**
     * Returns `true` if the shop search should be used. If FINDOLOGIC can be used `false` is returned.
     * Shop search will be used if the search was triggered
     * * via CLI.
     * * in the Shopware Backend.
     * * in "Einkaufswelten" aka. "Emotion".
     * * with FINDOLOGIC being disabled in the plugin config or no shopkey was set.
     * * when the shop is Direct Integration (Direct Integration will handle any search requests).
     * * on neither a search nor a navigation page.
     * * on a category page but FINDOLOGIC is disabled in category pages in the plugin config.
     * * when the shop is in Staging Mode and parameter `findologic=on` is not set.
     *
     * @return bool
     * @throws Zend_Cache_Exception
     */
    public static function useShopSearch()
    {
        $request = Shopware()->Front()->Request();
        $isCLIMode = $request === null;

        // Shop is not available in the Backend for the search of the Product Stream preview.
        // Shopware tries to create a new Session and throws an exception, because the Shop is not available

        if ($isCLIMode || !Shopware()->Container()->has('shop')) {
            return true;
        }

        $isInBackend = $request->getModuleName() === 'backend';
        $isEmotionPage = $request->getControllerName() === 'emotion';
        $isFindologicActive = static::isFindologicActive();
        $isDirectIntegration = static::checkDirectIntegration();
        $isActiveOnCategoryPages = (bool)Shopware()->Config()->offsetGet('ActivateFindologicForCategoryPages');

        $isCategoryPage = Shopware()->Session()->offsetGet('isCategoryPage');
        $isNoSearchAndCategoryPage = !$isCategoryPage && !Shopware()->Session()->offsetGet('isSearchPage');
        $isCategoryPageButDisabledInConfig = $isCategoryPage && !$isActiveOnCategoryPages;

        $fallbackSearchIsSet = static::checkIfFallbackSearchCookieIsSet();

        return (
            $isInBackend ||
            $isEmotionPage ||
            !$isFindologicActive ||
            $isDirectIntegration ||
            $isNoSearchAndCategoryPage ||
            $isCategoryPageButDisabledInConfig ||
            $fallbackSearchIsSet
        );
    }

    /**
     * Checks if FINDOLOGIC search has been activated properly.
     * * In the config FINDOLOGIC needs to be marked as enabled.
     * * A shopkey needs to be entered in the config.
     * * And shop is not staging
     *
     * @return bool
     * @throws Zend_Cache_Exception
     */
    public static function isFindologicActive()
    {
        /** @var Environment $environment */
        $environment = Shopware()->Container()->get('fin_search_unified.environment');

        $shopkey = trim(Shopware()->Config()->offsetGet('ShopKey'));
        $isStagingMode = $environment->isStaging(Shopware()->Front()->Request());
        $isActivateFindologic = (bool)Shopware()->Config()->offsetGet('ActivateFindologic');

        return !$isStagingMode && $isActivateFindologic && !empty($shopkey);
    }

    /**
     * @return mixed|null
     * @throws Zend_Cache_Exception
     */
    public static function checkDirectIntegration()
    {
        /** @var ConfigLoader $configLoader */
        $configLoader = Shopware()->Container()->get('fin_search_unified.config_loader');

        $integrationType = Shopware()->Config()->offsetGet(Constants::CONFIG_KEY_INTEGRATION_TYPE);
        $isDirectIntegration = $configLoader->directIntegrationEnabled(
            $integrationType === Constants::INTEGRATION_TYPE_DI
        );

        $integrationType = $isDirectIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API;
        static::storeIntegrationType($integrationType);

        return $isDirectIntegration;
    }

    /**
     * Saves the currently used integration type in the plugin configuration.
     *
     * @param string $currentIntegrationType
     */
    public static function storeIntegrationType($currentIntegrationType)
    {
        try {
            /** @var InstallerService $pluginManager */
            $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
            $plugin = $pluginManager->getPluginByName('FinSearchUnified');
            $config = $pluginManager->getPluginConfig($plugin);

            if (array_key_exists(Constants::CONFIG_KEY_INTEGRATION_TYPE, $config) &&
                $config[Constants::CONFIG_KEY_INTEGRATION_TYPE] !== $currentIntegrationType
            ) {
                $config[Constants::CONFIG_KEY_INTEGRATION_TYPE] = $currentIntegrationType;
                $pluginManager->savePluginConfig($plugin, $config);
            }
        } catch (Exception $exception) {
        }
    }

    /**
     * @param string $shopkey
     * @param string $usergroup
     *
     * @return string
     */
    public static function calculateUsergroupHash($shopkey, $usergroup)
    {
        return base64_encode($shopkey ^ $usergroup);
    }

    /**
     * @param string $shopkey
     * @param string $hash
     *
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
     *
     * @return bool
     */
    public static function stringEndsWith($needle, $haystack)
    {
        $start = -1 * strlen($needle);

        return substr($haystack, $start) === $needle;
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     */
    public static function setPromotion(SimpleXMLElement $xmlResponse)
    {
        /** @var SimpleXMLElement $promotion */
        $promotion = $xmlResponse->promotion;

        if (isset($promotion) && count($promotion->attributes()) > 0) {
            /** @var Enlight_View_Default $view */
            $view = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer')->Action()->View();
            $view->assign(
                [
                    'finPromotion' => [
                        'image' => $promotion->attributes()->image,
                        'link' => $promotion->attributes()->link
                    ]
                ]
            );
        }
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     */
    public static function setSmartDidYouMean(SimpleXMLElement $xmlResponse)
    {
        $query = $xmlResponse->query;
        $originalQuery = (string)$query->originalQuery;
        $didYouMeanQuery = (string)$query->didYouMeanQuery;
        $queryString = $query->queryString;
        $queryStringType = $queryString->attributes() !== null ? (string)$queryString->attributes()->type : null;

        if ((!empty($originalQuery) || !empty($didYouMeanQuery)) && $queryStringType !== 'forced') {
            /** @var Enlight_View_Default $view */
            $view = Shopware()->Front()->Plugins()->get('ViewRenderer')->Action()->View();
            $type = !empty($didYouMeanQuery) ? 'did-you-mean' : $queryStringType;
            $view->assign(
                [
                    'finSmartDidYouMean' => [
                        'type' => $type,
                        'alternative_query' => $type === 'did-you-mean' ? $didYouMeanQuery : $queryString,
                        'original_query' => $type === 'did-you-mean' ? '' : $originalQuery
                    ]
                ]
            );
        }
    }

    /**
     * @return bool
     */
    private static function checkIfFallbackSearchCookieIsSet()
    {
        return (isset($_COOKIE['fallback-search']) && (bool)$_COOKIE['fallback-search']) === true;
    }

    /**
     * @return bool
     */
    public static function isProductAndFilterLiveReloadingEnabled()
    {
        $listingMode = Shopware()->Config()->offsetGet('listingMode');

        return ($listingMode === 'filter_ajax_reload');
    }

    /**
     * @param SimpleXMLElement $xmlResponse
     */
    public static function setQueryInfoMessage(SimpleXMLElement $xmlResponse)
    {
        /** @var Enlight_View_Default $view */
        $view = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer')->Action()->View();

        $queryInfoMessageParser = new QueryInfoMessageParser($xmlResponse, $view);

        $view->assign(
            [
                'finQueryInfoMessage' => [
                    'filter_name' => $queryInfoMessageParser->getFilterName(),
                    'query' => $queryInfoMessageParser->getSmartQuery(),
                    'cat' => $queryInfoMessageParser->getCategory(),
                    'vendor' => $queryInfoMessageParser->getVendor()
                ],
                'snippetType' => $queryInfoMessageParser->getSnippetType()
            ]
        );
    }
}
