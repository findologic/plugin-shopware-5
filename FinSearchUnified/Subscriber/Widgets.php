<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use Exception;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Routing\Context;
use Shopware\Components\Routing\Matchers\RewriteMatcher;
use Shopware\Models\Shop\Shop;
use Shopware_Components_Config;
use Shopware_Controllers_Widgets_Listing;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class Widgets implements SubscriberInterface
{
    /**
     * @var Zend_Cache_Core
     */
    private $cache;

    /**
     * @var RewriteMatcher
     */
    private $rewriteMatcher;

    public function __construct(Zend_Cache_Core $cache, RewriteMatcher $rewriteMatcher)
    {
        $this->cache = $cache;
        $this->rewriteMatcher = $rewriteMatcher;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Widgets_Listing::listingCountAction::before' => 'beforeListingCountAction',
            'Enlight_Controller_Action_PreDispatch_Widgets' => 'onWidgetsPreDispatch'
        ];
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @throws Zend_Cache_Exception
     * @throws Exception
     */
    public function onWidgetsPreDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');
        $url = $this->parseReferer($request);

        if (strpos($url, 'search') !== false) {
            Shopware()->Session()->isSearchPage = true;
            Shopware()->Session()->isCategoryPage = false;
        } else {
            // If the URL is not a search page we will set the session value here
            Shopware()->Session()->isSearchPage = false;

            // Check if the category page is cached and set session value to true if it is
            $cacheKey = md5($url);
            $isCached = $this->cache->load($cacheKey);
            if ($isCached !== false) {
                Shopware()->Session()->isCategoryPage = true;

                return;
            }

            Shopware()->Session()->isCategoryPage = $this->isCategoryPage($url);
            $this->cache->save($cacheKey, Shopware()->Session()->isCategoryPage);
        }
    }

    public function beforeListingCountAction(Enlight_Hook_HookArgs $args)
    {
        if (!StaticHelper::useShopSearch()) {
            /** @var Shopware_Controllers_Widgets_Listing $subject */
            $subject = $args->getSubject();

            $request = $subject->Request();

            if (!$request->getParam('sSearch') && !$request->getParam('sCategory')) {
                $subject->Request()->setParam('sSearch', ' ');
            }
        }
    }

    /**
     * @param Enlight_Controller_Request_RequestHttp $request
     *
     * @return string
     * @throws Exception
     */
    private function parseReferer($request)
    {
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $url = parse_url($referrer, PHP_URL_PATH);

        $basePath = $request->getBasePath();
        if ($basePath !== '') {
            $basePath = rtrim($basePath, '/') . '/';
            $url = str_replace($basePath, '', $url);
        }
        $url = ltrim($url, '/');

        return $url;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    private function isCategoryPage($url)
    {
        /** @var Shop $shop */
        $shop = Shopware()->Container()->get('shop');

        /** @var Shopware_Components_Config $config */
        $config = Shopware()->Container()->get('config');

        /** @var Context $context */
        $context = Context::createFromShop($shop, $config);

        $rewrite = $this->rewriteMatcher->match($url, $context);
        if (is_string($rewrite)) {
            // As Shopware require the trailing slashes in the URL to match the category URL
            // we will append the slash manually to check if it really is a category page even when there is
            // no trailing slash present in the URL
            $url = rtrim($url, '/') . '/';
            $rewrite = $this->rewriteMatcher->match($url, $context);
        }

        // Only if the matcher finds the category page, we will return true, otherwise it will always return false
        $isCategoryPage = false;

        if (is_array($rewrite)) {
            $rewrite['module'] = 'frontend';
            $rewrite['controller'] = 'cat';
            $rewrite['action'] = 'index';
            $isCategoryPage = true;
        }

        return $isCategoryPage;
    }
}
