<?php

namespace FinSearchUnified\Bundle\ControllerBundle\Listener;

use Enlight_Event_EventArgs;

class ControllerPathListener
{
    /**
     * @var string[]
     */
    private $controllers = [];

    /**
     * @param string $event
     * @param string $path
     */
    public function addController($event, $path)
    {
        $this->controllers[strtolower($event)] = $path;
    }

    /**
     * @return string|null
     */
    public function getControllerPath(Enlight_Event_EventArgs $args)
    {
        $name = strtolower($args->getName());
        if (array_key_exists($name, $this->controllers)) {
            return $this->controllers[$name];
        }

        return null;
    }
}
