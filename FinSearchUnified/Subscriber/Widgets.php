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
    private $rewrite;

    public function __construct(Zend_Cache_Core $cache, RewriteMatcher $rewrite)
    {
        $this->cache = $cache;
        $this->rewrite = $rewrite;
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
     */
    public function onWidgetsPreDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request = $args->get('request');
        $url = $this->parseReferUrl($request);

        if (strpos($url, 'search') !== false) {
            Shopware()->Session()->isSearchPage = true;
            Shopware()->Session()->isCategoryPage = false;
        } else {
            Shopware()->Session()->isSearchPage = false;
            $cacheKey = md5($url);
            $isCached = $this->cache->test($cacheKey);
            if ($isCached !== null && $isCached !== false) {
                Shopware()->Session()->isCategoryPage = true;

                return;
            }
            $context = Context::createFromShop(
                Shopware()->Container()->get('shop'),
                Shopware()->Container()->get('config')
            );

            $rewrite = $this->rewrite->match($url, $context);
            if (is_string($rewrite)) {
                Shopware()->Session()->isCategoryPage = false;
            } elseif (is_array($rewrite)) {
                $rewrite['module'] = 'frontend';
                $rewrite['controller'] = 'cat';
                $rewrite['action'] = 'index';
                Shopware()->Session()->isCategoryPage = true;
            } else {
                Shopware()->Session()->isCategoryPage = false;
            }

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
    private function parseReferUrl($request)
    {
        $referrer = $request->getHeader('referer');
        $url = parse_url($referrer, PHP_URL_PATH);

        $basePath = $request->getBasePath();
        if ($basePath !== '') {
            $basePath = rtrim($basePath, '/') . '/';
            $url = str_replace($basePath, '', $url);
        }
        $url = ltrim($url, '/');

        return $url;
    }
}
