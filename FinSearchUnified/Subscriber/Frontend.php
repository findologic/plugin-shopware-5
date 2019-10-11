<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_Request;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use Enlight_Template_Manager;
use Exception;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Theme\LessDefinition;
use Shopware_Controllers_Frontend_Search;

class Frontend implements SubscriberInterface
{
    const WHITE_LIST = [
        'listing',
        'search',
        'media',
        'checkout' => [
            'ajaxAddArticle',
            'ajaxAddArticleCart',
            'ajaxDeleteArticleCart',
            'ajaxCart',
            'ajaxAmount'
        ]
    ];

    /**
     * @var string
     */
    public $shopKey;

    /**
     * @var string
     */
    private $pluginDirectory;

    /**
     * @var Enlight_Template_Manager
     */
    private $templateManager;

    /**
     * @param string $pluginDirectory
     * @param Enlight_Template_Manager $templateManager
     */
    public function __construct($pluginDirectory, Enlight_Template_Manager $templateManager)
    {
        $this->pluginDirectory = $pluginDirectory;
        $this->templateManager = $templateManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Frontend_Search::indexAction::before' => 'beforeSearchIndexAction',
            'Enlight_Controller_Action_PreDispatch' => 'onPreDispatch',
            'Enlight_Controller_Action_Frontend_AjaxSearch_Index' => 'onAjaxSearchIndexAction',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Findologic' => 'onFindologicController',
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'onFrontendPreDispatch',
            'Enlight_Controller_Front_RouteStartup' => 'onRouteStartup',
            'Theme_Compiler_Collect_Plugin_Less' => 'onCollectPluginLess'
        ];
    }

    public function onCollectPluginLess(Enlight_Event_EventArgs $args)
    {
        return new LessDefinition(
            [],
            [$this->pluginDirectory . '/Resources/views/frontend/less/all.less']
        );
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onRouteStartup(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_Request $request */
        $request = $args->get('request');
        if ($this->isLegacySearch($request)) {
            $params = $request->getQuery();

            unset($params['module'], $params['controller'], $params['action']);

            $url = '/search?' . http_build_query($params, null, '&', PHP_QUERY_RFC3986);
            // Perform a redirect to the actual search path and tell the client that it moved permanently.
            // The legacy parameters (relevant for search) will be mapped automatically by the corresponding
            // event handler.
            $args->get('response')->setRedirect($url, 301);
        }
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendPreDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_Request $request */
        $request = $args->get('request');
        if ($this->isSearchPage($request)) {
            Shopware()->Session()->offsetSet('isSearchPage', true);
            Shopware()->Session()->offsetSet('isCategoryPage', false);
        } elseif ($this->isCategoryPage($request)) {
            Shopware()->Session()->offsetSet('isCategoryPage', true);
            Shopware()->Session()->offsetSet('isSearchPage', false);
        } elseif ($this->isManufacturerPage($request) || !$this->isWhiteListed($request)) {
            Shopware()->Session()->offsetSet('isCategoryPage', false);
            Shopware()->Session()->offsetSet('isSearchPage', false);
        } else {
            // Keep the flags as they are since these might be subsequent requests from the same page.
        }
    }

    /**
     * @param Enlight_Hook_HookArgs $args
     *
     * @throws Exception
     */
    public function beforeSearchIndexAction(Enlight_Hook_HookArgs $args)
    {
        /** @var Shopware_Controllers_Frontend_Search $subject */
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();
        $mappedParams = [];

        if ((array_key_exists('sSearch', $params) && empty($params['sSearch'])) ||
            (Shopware()->Session()->offsetGet('isSearchPage') && !array_key_exists('sSearch', $params))
        ) {
            $request->setParam('sSearch', ' ');
            unset($params['sSearch']);
        }

        if (array_key_exists('catFilter', $params)) {
            $mappedParams['cat'] = rawurldecode($params['catFilter']);
            unset($params['catFilter']);
        }

        if (array_key_exists('attrib', $params)) {
            foreach ($params['attrib'] as $filterName => $filterValue) {
                if ($filterName === 'wizard') {
                    continue;
                } elseif ($filterName === 'price') {
                    foreach ($filterValue as $key => $value) {
                        $mappedParams[$key] = $value;
                    }
                } else {
                    $mappedValue = is_array($filterValue) ? implode('|', $filterValue) : $filterValue;
                    $mappedParams[$filterName] = rawurldecode($mappedValue);
                }
            }

            unset($params['attrib']);
        }

        if ($mappedParams) {
            $path = sprintf('%s/%s', $request->getBaseUrl(), $params['controller']);
            $mappedParams = array_merge($params, $mappedParams);

            // Explicitly re-add this parameter only if there were parameters to be mapped.
            // This will avoid a redirect loop.
            if ($request->has('sSearch')) {
                $mappedParams['sSearch'] = $request->getParam('sSearch');
            }

            $request->setParams($mappedParams);

            unset($mappedParams['module']);
            unset($mappedParams['controller']);
            unset($mappedParams['action']);

            $request->setRequestUri($path . '?' . http_build_query($mappedParams, null, '&', PHP_QUERY_RFC3986));
            $args->setReturn($request);

            $subject->redirect($request->getRequestUri());
        }
    }

    public function onPreDispatch()
    {
        $this->templateManager->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    /**
     * @param Enlight_Event_EventArgs $args
     */
    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        if (!(bool)Shopware()->Config()->get('ActivateFindologic')) {
            return;
        }

        $groupKey = Shopware()->Session()->get('sUserGroup', 'EK');
        $hash = StaticHelper::calculateUsergroupHash($this->getShopKey(), $groupKey);

        $searchResultContainer = Shopware()->Config()->get('SearchResultContainer');
        $navigationContainer = Shopware()->Config()->get('NavigationContainer');

        try {
            /** @var Enlight_Controller_ActionEventArgs $args */
            $view = $args->getSubject()->View();
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views/');
            $view->extendsTemplate('frontend/fin_search_unified/header.tpl');
            $view->assign('userGroupHash', $hash);
            $view->assign('hashedShopkey', strtoupper(md5($this->getShopKey())));
            $view->assign('searchResultContainer', $searchResultContainer);
            $view->assign('navigationContainer', $navigationContainer);
        } catch (Exception $e) {
            //TODO LOGGING
        }
    }

    /**
     * @return mixed
     */
    private function getShopKey()
    {
        $shopKey = Shopware()->Config()->get('ShopKey');

        if ($shopKey) {
            $this->shopKey = $shopKey;
        }

        return $this->shopKey;
    }

    /**
     * @param Enlight_Event_EventArgs $args
     *
     * @return string
     */
    public function onFindologicController(Enlight_Event_EventArgs $args)
    {
        return $this->pluginDirectory . 'Controllers/Frontend/Findologic.php';
    }

    /**
     * @return bool
     */
    public function onAjaxSearchIndexAction()
    {
        if (StaticHelper::isFindologicActive()) {
            Shopware()->Container()->get('front')->Plugins()->ViewRenderer()->setNoRender();

            return true;
        }
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    private function isSearchPage(Enlight_Controller_Request_Request $request)
    {
        return array_key_exists('sSearch', $request->getParams());
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    private function isCategoryPage(Enlight_Controller_Request_Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() !== 'manufacturer' &&
            array_key_exists('sCategory', $request->getParams());
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    private function isManufacturerPage(Enlight_Controller_Request_Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() === 'manufacturer';
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    public function isWhiteListed(Enlight_Controller_Request_Request $request)
    {
        $controllerName = strtolower($request->getControllerName());

        foreach (self::WHITE_LIST as $name => $actions) {
            // If the requested controller and actions are whitelisted then return true
            if ($controllerName === $actions ||
                ($controllerName === $name && in_array($request->getActionName(), $actions))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Enlight_Controller_Request_Request $request
     *
     * @return bool
     */
    private function isLegacySearch(Enlight_Controller_Request_Request $request)
    {
        return strpos($request->getRequestUri(), '/FinSearchAPI/search') !== false;
    }
}
