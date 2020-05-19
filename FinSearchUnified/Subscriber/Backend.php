<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Backend implements SubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_FindologicStaging' => 'onGetBackendController',
        ];
    }

    /**
     * adds the templates and snippets dir
     *
     * @return string
     */
    public function onGetBackendController()
    {
        $this->container->get('template')->addTemplateDir($this->getPluginPath() . '/Resources/views/');
        $this->container->get('snippets')->addConfigDir($this->getPluginPath() . '/Resources/snippets/');

        return $this->getPluginPath() . '/Controllers/Backend/FindologicStaging.php';
    }

    /**
     * @return string
     */
    private function getPluginPath()
    {
        return $this->container->getParameter('fin_search_unified.plugin_dir');
    }
}
