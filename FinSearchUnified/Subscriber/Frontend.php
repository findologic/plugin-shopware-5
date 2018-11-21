<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use FinSearchUnified\Helper\StaticHelper;
use Enlight_Controller_Request_Request as Request;

class Frontend implements SubscriberInterface
{
    /**
     * @var string
     */
    public $shopKey;

    /**
     * @var string
     */
    private $pluginDirectory;

    /**
     * @var \Enlight_Template_Manager
     */
    private $templateManager;

    /**
     * @param string $pluginDirectory
     * @param \Enlight_Template_Manager $templateManager
     */
    public function __construct($pluginDirectory, \Enlight_Template_Manager $templateManager)
    {
        $this->pluginDirectory = $pluginDirectory;
        $this->templateManager = $templateManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Frontend_Search::indexAction::before' => 'beforeSearchIndexAction',
            'Enlight_Controller_Action_PreDispatch'                     => 'onPreDispatch',
            'Enlight_Controller_Action_Frontend_AjaxSearch_Index'       => 'onAjaxSearchIndexAction',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend'     => 'onFrontendPostDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Findologic'   => 'onFindologicController',
            'Enlight_Controller_Action_PreDispatch_Frontend'            => 'onFrontendPreDispatch'
        ];
    }

    public function onFrontendPreDispatch(Enlight_Event_EventArgs $args)
    {
        $subject = $args->getSubject();

        /** @var Request $request */
        $request = $subject->Request();

        if ($this->isSearchPage($request)) {
            Shopware()->Session()->offsetSet('isSearchPage', true);
            Shopware()->Session()->offsetSet('isCategoryPage', false);
        } elseif ($this->isCategoryPage($request)) {
            Shopware()->Session()->offsetSet('isCategoryPage', true);
            Shopware()->Session()->offsetSet('isSearchPage', false);
        } elseif ($this->isManufacturerPage($request)) {
            Shopware()->Session()->offsetSet('isCategoryPage', false);
            Shopware()->Session()->offsetSet('isSearchPage', false);
        } else {
            // Keep the flags as they are since these might be subsequent requests from the same page.
        }
    }

    public function beforeSearchIndexAction(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Search $subject */
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
            $mappedParams['cat'] = urldecode($params['catFilter']);
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
                    $mappedParams[$filterName] = urldecode($mappedValue);
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

            $request->setRequestUri($path . '?' . http_build_query($mappedParams));
            $args->setReturn($request);

            $subject->redirect($request->getRequestUri());
        }
    }

    public function onPreDispatch()
    {
        $this->templateManager->addTemplateDir($this->pluginDirectory.'/Resources/views');
    }

    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        if (!(bool) Shopware()->Config()->get('ActivateFindologic')) {
            return;
        }

        $groupKey = Shopware()->Session()->get('sUserGroup', 'EK');
        $hash = StaticHelper::calculateUsergroupHash($this->getShopKey(), $groupKey);

        try {
            /** @var \Enlight_Controller_ActionEventArgs $args */
            $view = $args->getSubject()->View();
            $view->addTemplateDir($this->pluginDirectory.'/Resources/views/');
            $view->extendsTemplate('frontend/fin_search_unified/header.tpl');
            $view->assign('userGroupHash', $hash);
            $view->assign('hashedShopkey', strtoupper(md5($this->getShopKey())));
        } catch (\Enlight_Exception $e) {
            //TODO LOGGING
        }
    }

    private function getShopKey()
    {
        $shopKey = Shopware()->Config()->get('ShopKey');

        if ($shopKey) {
            $this->shopKey = $shopKey;
        }

        return $this->shopKey;
    }

    public function onFindologicController(\Enlight_Event_EventArgs $args)
    {
        return $this->pluginDirectory . 'Controllers/Frontend/Findologic.php';
    }

    public function onAjaxSearchIndexAction()
    {
        if (StaticHelper::isFindologicActive()) {
            Shopware()->Container()->get('front')->Plugins()->ViewRenderer()->setNoRender();
            return true;
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function isSearchPage(Request $request)
    {
        return array_key_exists('sSearch', $request->getParams());
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function isCategoryPage(Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() !== 'manufacturer' &&
            array_key_exists('sCategory', $request->getParams());
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function isManufacturerPage(Request $request)
    {
        return $request->getControllerName() === 'listing' && $request->getActionName() === 'manufacturer';
    }
}
