<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;

class ServiceSubscriber implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $handler = ['registerProductNumberSearch', -5000];

        return [
            'Enlight_Bootstrap_AfterInitResource_shopware_search.product_number_search' => $handler
        ];
    }

    public function registerProductNumberSearch()
    {
        if (!$this->container->getParameter('shopware.es.enabled')) {
            return;
        }
        $this->container->set(
            'shopware_search.product_number_search',
            $this->container->get('shopware_search_es.product_number_search')
        );
    }
}
