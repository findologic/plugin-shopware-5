<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\ShopwareProcess;

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
            'Enlight_Controller_Action_PreDispatch_Frontend_Listing'    => 'onFrontendListingPreDispatch',
            'Enlight_Controller_Action_PreDispatch_Frontend'            => 'onFrontendPreDispatch',
            //'Shopware\Models\Article\Price::postUpdate'                 => 'onPostPersist',
            //'Shopware\Models\Article\Price::postPersist'                => 'onPostPersist',
            'Shopware\Models\Article\Article::postUpdate'               => 'onPostPersist',
            'Shopware\Models\Article\Article::postPersist'              => 'onPostPersist',
            //'Shopware\Models\Article\Detail::postUpdate'                => 'onPostPersist',
            //'Shopware\Models\Article\Detail::postPersist'               => 'onPostPersist',
            'Shopware\Models\Category\Category::postPersist'            => 'onPostPersist',
            'Shopware\Models\Category\Category::postUpdate'             => 'onPostPersist',
            'Shopware\Models\ProductStream\ProductStream::postUpdate'            => 'onPostPersist',
            'Shopware\Models\ProductStream\ProductStream::postPersist'            => 'onPostPersist',
            'Shopware\Models\ProductStream\ProductStream::postRemove'            => 'onPostPersist',
        ];
    }

    public function onFrontendListingPreDispatch()
    {
        Shopware()->Session()->offsetSet('isCategoryPage', true);
    }

    public function onFrontendPreDispatch()
    {
        Shopware()->Session()->offsetSet('isCategoryPage', false);
    }

    public function beforeSearchIndexAction(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Search $subject */
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();
        $mappedParams = [];

        if (array_key_exists('sSearch', $params) && empty($params['sSearch'])) {
            $request->setParams(['sSearch' => ' ']);
            unset($params['sSearch']);
        }

        if (array_key_exists('attrib', $params)) {
            foreach ($params['attrib'] as $filterName => $filterValue) {
                if ($filterName === 'wizard') {
                    continue;
                }

                $mappedParams[$filterName] = is_array($filterValue) ? implode('|', $filterValue) : $filterValue;
            }

            unset($params['attrib']);
        }

        if ($mappedParams) {
            $path = strstr($request->getRequestUri(), '?', true);
            $request->setParams(array_merge($params, $mappedParams));
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
        $groupKey = Shopware()->Session()->sUserGroup;

        $shopKey = $this->getShopKey();
        $hash = '?usergrouphash=';

        if (empty($groupKey)) {
            $groupKey = 'EK';
        }
        $hash .= ShopwareProcess::calculateUsergroupHash($shopKey, $groupKey);
        $format = 'https://cdn.findologic.com/static/%s/main.js%s';
        $mainUrl = sprintf($format, strtoupper(md5($shopKey)), $hash);

        try {
            /** @var \Enlight_Controller_ActionEventArgs $args */
            $view = $args->getSubject()->View();
            $view->addTemplateDir($this->pluginDirectory.'/Resources/views/');
            $view->extendsTemplate('frontend/fin_search_unified/header.tpl');
            $view->assign('mainUrl', $mainUrl);
        } catch (\Enlight_Exception $e) {
            //TODO LOGGING
        }
    }

    public function onPostPersist() {
        $cache = Shopware()->Container()->get('cache');
        if (!$cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['findologic'])) {
            $cache->remove(StaticHelper::getProductStreamKey() . 'articles');
            $cache->remove(StaticHelper::getProductStreamKey() . 'categories');
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
