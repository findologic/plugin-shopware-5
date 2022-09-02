<?php

namespace FinSearchUnified\Helper;

use Enlight_Controller_Plugins_ViewRenderer_Bootstrap;
use Enlight_Controller_Request_Request;
use Enlight_View_Default;
use Exception;
use FINDOLOGIC\Api\Definitions\QueryParameter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Promotion;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\QueryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\SmartDidYouMean;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Components\Environment;
use FinSearchUnified\Constants;
use Shopware;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Bundle\StoreFrontBundle;
use SimpleXMLElement;
use Zend_Cache_Exception;

use function getenv;

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
     * @see https://stackoverflow.com/a/7974253
     *
     * @param $url
     * @return array|string|string[]|null
     */
    public static function encodeUrlPath($url)
    {
        return preg_replace_callback('#://([^/]+)/([^?]+)#', function ($match) {
            return '://' . $match[1] . '/' . join('/', array_map('rawurlencode', explode('/', $match[2])));
        }, $url);
    }

    /**
     * @param string $responseText
     *
     * @return SimpleXMLElement
     */
    public static function getXmlFromRawResponse($responseText)
    {
        return new SimpleXMLElement($responseText);
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
        $string = html_entity_decode($string);

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

        $isCategoryPage = Shopware()->Session()->offsetGet('isCategoryPage') || static::isCategoryPage($request);
        $isManufacturerPage = Shopware()->Session()->offsetGet('isManufacturerPage') ||
            static::isManufacturerPage($request);
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
            $isManufacturerPage ||
            $fallbackSearchIsSet
        );
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    public static function isCategoryPage(Enlight_Controller_Request_Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() !== 'manufacturer' &&
            array_key_exists('sCategory', $request->getParams());
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    public static function isManufacturerPage(Enlight_Controller_Request_Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() === 'manufacturer';
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

        $shopkey = trim(Shopware()->Config()->getByNamespace('FinSearchUnified', 'ShopKey'));
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
     * @param Promotion|null $promotion
     */
    public static function setPromotion(Promotion $promotion = null)
    {
        if ($promotion !== null) {
            /** @var Enlight_Controller_Plugins_ViewRenderer_Bootstrap $viewRenderer */
            $viewRenderer = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer');
            $view = $viewRenderer->Action()->View();
            $view->assign(
                [
                    'finPromotion' => [
                        'image' => $promotion->getImage(),
                        'link' => $promotion->getLink()
                    ]
                ]
            );
        }
    }

    /**
     * @param SmartDidYouMean $smartDidYouMean
     */
    public static function setSmartDidYouMean(SmartDidYouMean $smartDidYouMean)
    {
        /** @var Enlight_Controller_Plugins_ViewRenderer_Bootstrap $viewRenderer */
        $viewRenderer = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer');
        $view = $viewRenderer->Action()->View();
        $view->assign(
            [
                'finSmartDidYouMean' => [
                    'type' => $smartDidYouMean->getType(),
                    'alternative_query' => $smartDidYouMean->getAlternativeQuery(),
                    'original_query' => $smartDidYouMean->getOriginalQuery(),
                    'link' => $smartDidYouMean->getLink()
                ]
            ]
        );
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
     * @param QueryInfoMessage $queryInfoMessage
     */
    public static function setQueryInfoMessage(QueryInfoMessage $queryInfoMessage)
    {
        /** @var Enlight_View_Default $view */
        $view = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer')->Action()->View();

        $view->assign(
            [
                'finQueryInfoMessage' => [
                    'filter_name' => $queryInfoMessage->getFilterName(),
                    'filter_value' => $queryInfoMessage->getFilterValue(),
                    'query' => $queryInfoMessage->getQuery()
                ],
                'snippetType' => $queryInfoMessage->getType()
            ]
        );
    }

    public static function setPushAttribs()
    {
        /** @var Enlight_View_Default $view */
        $view = Shopware()->Container()->get('front')->Plugins()->get('ViewRenderer')->Action()->View();
        $pushAttribs = Shopware()->Front()->Request()->getParam(QueryParameter::PUSH_ATTRIB);

        if ($pushAttribs) {
            $view->assign(
                [
                    'finPushAttribs' => $pushAttribs
                ]
            );
        }
    }

    /**
     * @param $version
     *
     * @return bool
     */
    public static function isVersionLowerThan($version)
    {
        $shopwareVersion = static::getShopwareVersion();

        return version_compare($shopwareVersion, $version, '<');
    }

    public static function getShopwareVersion()
    {
        $version = null;
        if (Shopware()->Container()->has('shopware.release.version')) {
            $version = Shopware()->Container()->get('shopware.release.version');
        } elseif (defined('\Shopware::VERSION')) {
            $version = Shopware::VERSION;
        }

        if (!$version || $version === '___VERSION___') {
            $version = getenv('SHOPWARE_VERSION') ?: '5.6.9';
        }

        return ltrim($version, 'v');
    }

    public static function getPreferredImage(
        $image,
        $thumbnails,
        $imageSize,
        $preferredExportWidth,
        $preferredExportHeight
    ) {
        $preferredExportResolution = sprintf("%dx%d", $preferredExportWidth, $preferredExportHeight);
        if (array_key_exists($preferredExportResolution, $thumbnails)) {
            $image = $thumbnails[$preferredExportResolution];
        } elseif ($imageSize < $preferredExportWidth * $preferredExportHeight) {
            foreach (array_keys($thumbnails) as $resolution) {
                $dimensions = explode('x', $resolution);
                $thumbnailsSizes[$resolution] = intval($dimensions[0]) * intval($dimensions[1]);
            }

            ksort($thumbnailsSizes, SORT_NUMERIC);

            foreach ($thumbnailsSizes as $key => $thumbnailsSize) {
                if ($thumbnailsSize > $imageSize) {
                    return $thumbnails[$key];
                }
            }
        }

        return $image;
    }
}
