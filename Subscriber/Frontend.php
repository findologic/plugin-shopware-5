<?php

namespace findologicDI\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use findologicDI\ShopwareProcess;

class Frontend implements SubscriberInterface {

	var $shopKey;

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
	public function __construct( $pluginDirectory, \Enlight_Template_Manager $templateManager ) {
		$this->pluginDirectory = $pluginDirectory;
		$this->templateManager = $templateManager;
	}

	public static function getSubscribedEvents() {
		return array(
			'Enlight_Controller_Action_PreDispatch'                   => 'onPreDispatch',
			'Enlight_Controller_Action_PostDispatchSecure_Frontend'   => 'onFrontendPostDispatch',
			'Enlight_Controller_Dispatcher_ControllerPath_Findologic' => 'onFindologicController',
			'Enlight_Controller_Action_PreDispatch_Backend_Form' => 'onLoadPluginManager'
		);
	}

	public function onLoadPluginManager( \Enlight_Event_EventArgs $args ) {
		/** @var \Shopware_Controllers_Backend_Form $subject */
		$subject = $args->getSubject();
	}



	public function onPreDispatch() {
		$this->templateManager->addTemplateDir( $this->pluginDirectory . '/Resources/views' );
	}

	public function onFrontendPostDispatch( Enlight_Event_EventArgs $args ) {

		if (!(bool)Shopware()->Config()->get( 'ActivateFindologic' )){
			return;
		}
		$groupKey = Shopware()->Session()->sUserGroup;

		$shopKey = $this->getShopKey();
		$hash    = '?usergrouphash=';

		if ( empty( $groupKey ) ) {
			$groupKey = 'EK';
		}
		$hash .= ShopwareProcess::calculateUsergroupHash($shopKey, $groupKey);
		$format  = 'https://cdn.findologic.com/static/%s/main.js%s';
		$mainUrl = sprintf( $format, strtoupper( md5( $shopKey ) ), $hash );
		try {
			/** @var \Enlight_Controller_ActionEventArgs $args */
			$view = $args->getSubject()->View();
			$view->addTemplateDir( $this->pluginDirectory . '/Resources/views/' );
			$view->extendsTemplate( 'frontend/findologic_d_i/header.tpl' );
			$view->assign( 'mainUrl', $mainUrl );
		} catch ( \Enlight_Exception $e ) {
			//TODO LOGGING
		}

	}

	private function getShopKey() {
		$shopKey = Shopware()->Config()->get( 'ShopKey' );

		if ( $shopKey ) {
			$this->shopKey = $shopKey;
		}

		return $this->shopKey;
	}

	public function onFindologicController( \Enlight_Event_EventArgs $args ) {
		return $this->Path() . 'Controllers/Frontend/Findologic.php';
	}


}
