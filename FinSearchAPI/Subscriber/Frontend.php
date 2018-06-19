<?php

namespace FinSearchAPI\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Event_EventArgs;
use FinSearchAPI\ShopwareProcess;

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
            'Enlight_Controller_Action_PreDispatch'                     => 'onPreDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend'     => 'onFrontendPostDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Findologic'   => 'onFindologicController',
            'Enlight_Controller_Action_PreDispatch_Backend_Form'        => 'onLoadPluginManager',
            'Enlight_Controller_Action_PreDispatch_Frontend_AjaxSearch' => 'onAjaxLoad',
        ];
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onAjaxLoad(\Enlight_Event_EventArgs $args)
    {
        if ((bool)Shopware()->Config()->get('ActivateFindologic')) {
            return;
        }
    }

    public function onLoadPluginManager(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Form $subject */
        $subject = $args->getSubject();
    }

    public function onPreDispatch()
    {
        $this->templateManager->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args)
    {
        if (!(bool)Shopware()->Config()->get('ActivateFindologic')) {
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
            $view->addTemplateDir($this->pluginDirectory . '/Resources/views/');
            $view->extendsTemplate('frontend/fin_search_api/header.tpl');
            $view->assign('mainUrl', $mainUrl);
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
        return $this->Path() . 'Controllers/Frontend/Findologic.php';
    }
}
