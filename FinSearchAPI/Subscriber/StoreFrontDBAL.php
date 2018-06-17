<?php
/**
 * Created by PhpStorm.
 * User: wege
 * Date: 17.06.2018
 * Time: 10:19.
 */

namespace FinSearchAPI\Subscriber;

use Enlight\Event\SubscriberInterface;
use FinSearchAPI\Bundles\SearchBundleDBAL\CriteriaRequestHandler;

class StoreFrontDBAL implements SubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (position defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     * <code>
     * return array(
     *     'eventName0' => 'callback0',
     *     'eventName1' => array('callback1'),
     *     'eventName2' => array('callback2', 10),
     *     'eventName3' => array(
     *         array('callback3_0', 5),
     *         array('callback3_1'),
     *         array('callback3_2')
     *     )
     * );
     *
     * </code>
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_SearchBundle_Collect_Criteria_Request_Handlers' => 'registerRequestHandlers',
        ];
    }

    public function registerRequestHandlers()
    {
        return new CriteriaRequestHandler();
    }
}
