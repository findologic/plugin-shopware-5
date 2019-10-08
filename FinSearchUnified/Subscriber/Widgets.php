<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
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
        $referrer = $request->getHeader('referer');
        $url = $this->parseReferUrl($referrer);

        if (strpos($url, 'search') !== false) {
            Shopware()->Session()->isSearchPage = true;
            Shopware()->Session()->isCategoryPage = false;
        } else {
            Shopware()->Session()->isSearchPage = false;
            $cacheKey = md5($url);
            $isCategoryPage = $this->cache->test($cacheKey);
            var_dump(['$isCategoryPage' => $isCategoryPage]);
            var_dump(['$cacheKey' => $cacheKey]);
            if ($isCategoryPage !== false) {
                Shopware()->Session()->isCategoryPage = true;

                return;
            }
            $context = Context::createFromShop(
                Shopware()->Container()->get('shop'),
                Shopware()->Container()->get('config')
            );

            $rewrite = $this->rewrite->match($url, $context);
            var_dump(['$rewrite' => $rewrite]);
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
     * @param string $referrer
     *
     * @return string
     */
    private function parseReferUrl($referrer)
    {
        $url = parse_url($referrer, PHP_URL_PATH);
        $url = str_replace('/', '', $url);

        $basePath = Shopware()->Container()->get('shop')->getBasePath();
        $basePath = rtrim($basePath, '/') . '/';

        $url = str_replace($basePath, '', $url);

        return rtrim($url, '/') . '/';
    }
}
