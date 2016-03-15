<?php

/**
 * Class CustomExport
 */
class FindologicCustomExport
{
    /**
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param SimpleXMLElement $properties XML node to render to.
     * @param Shopware_Controllers_Frontend_Findologic $main
     */
    public function addCustomProperty($properties, $article, $main)
    {
        $main->addProperty($properties, 'customProperty', $article->getHighlight());
    }

}