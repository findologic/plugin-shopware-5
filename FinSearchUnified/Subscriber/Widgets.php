<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Hook_HookArgs;
use FinSearchUnified\Helper\StaticHelper;
use Shopware_Controllers_Widgets_Listing;


class Widgets implements SubscriberInterface
{
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

    private function __construct()
    {
        Shopware()->Container()->get('cache');
        Shopware()->Container()->get('shopware.routing.matchers.rewrite_matcher');
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $args
     */
    public function onWidgetsPreDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $request = $this->getRequest();
        $request->getHeaders();

        $referrer = $request->getHeader('');


    }
}
