<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use FinSearchUnified\Helper\StaticHelper;

class Frontend implements SubscriberInterface
{
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
     * @param $pluginDirectory
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
        $request = $subject->Request();
        $params = $request->getParams();

        if ($params['controller'] === 'search') {
            Shopware()->Session()->offsetSet('isSearchPage', true);
            Shopware()->Session()->offsetSet('isCategoryPage', false);
        }

        if (array_key_exists('sCategory', $params)) {
            Shopware()->Session()->offsetSet('isCategoryPage', true);
            Shopware()->Session()->offsetSet('isSearchPage', false);
        }
    }

    public function beforeSearchIndexAction(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Search $subject */
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();
        $mappedParams = [];

        if (
            (array_key_exists('sSearch', $params) && empty($params['sSearch'])) ||
            (Shopware()->Session()->offsetGet('isSearchPage') && !array_key_exists('sSearch', $params))
        ) {
            $request->setParam('sSearch', ' ');
            unset($params['sSearch']);
        }

        if (array_key_exists('catFilter', $params)) {
            $mappedParams['cat'] = $params['catFilter'];
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
                    $mappedParams[$filterName] = is_array($filterValue) ? implode('|', $filterValue) : $filterValue;
                }
            }

            unset($params['attrib']);
        }

        if ($mappedParams) {
            $path = strstr($request->getRequestUri(), '?', true);
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
        return $this->Path().'Controllers/Frontend/Findologic.php';
    }

    public function onAjaxSearchIndexAction()
    {
        if (StaticHelper::isFindologicActive()) {
            Shopware()->Container()->get('front')->Plugins()->ViewRenderer()->setNoRender();
            return true;
        }
    }
}
