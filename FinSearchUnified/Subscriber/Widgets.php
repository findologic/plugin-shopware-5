<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Hook_HookArgs;
use FinSearchUnified\Helper\StaticHelper;

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
            'Shopware_Controllers_Widgets_Listing::listingCountAction::before' => 'beforeListingCountAction'
        ];
    }

    public function beforeListingCountAction(Enlight_Hook_HookArgs $args)
    {
        if (StaticHelper::useShopSearch()) {
            return;
        }

        /** @var \Shopware_Controllers_Widgets_Listing $subject */
        $subject = $args->getSubject();

        $params = $subject->Request()->getParams();

        if (!isset($params['sSearch']) && !isset($params['sCategory'])) {
            $subject->Request()->setParam('sSearch', ' ');
        }
    }
}
