<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;

class RegisterComponents implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginPath;

    public function __construct($pluginDirectory)
    {
        $this->pluginPath = $pluginDirectory;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_StartDispatch' => 'registerComponents',
            'Shopware_Console_Add_Command' => 'registerComponents'
        ];
    }

    public function registerComponents()
    {
        require_once $this->pluginPath . '/vendor/autoload.php';
    }
}
