<?php

namespace findologicDI\Subscriber;

use Enlight\Event\SubscriberInterface;

class Frontend implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',
            'Enlight_Controller_Dispatcher_ControllerPath_Findologic' => 'onFindologicController'
        );
    }

    public function onFindologicController(\Enlight_Event_EventArgs $args)
    {
        return $this->Path() . 'Controllers/Frontend/Findologic.php';
    }

    public function onFrontendPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var $controller \Enlight_Controller_Action */
        $controller = $args->getSubject();
        $view = $controller->View();
    }
}
