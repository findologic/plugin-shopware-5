<?php

/**
 * Class FindologicCustomExport
 */
class FindologicCustomExport
{
    /**
     * @param SimpleXMLElement $properties XML node to render to.
     * @param Shopware_Controllers_Frontend_Findologic $main
     */
    public function addCustomProperty($properties, $main)
    {
        $main->addProperty($properties, 'custom_property_1', 'some value 1');
        $main->addProperty($properties, 'custom_property_2', 'some value 2');
        $main->addProperty($properties, 'custom_property_3', 'some value 3');
    }

}