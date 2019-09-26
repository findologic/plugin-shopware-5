<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use SimpleXMLElement;

class CustomListingHydrator
{
    /**
     * @param SimpleXMLElement $select
     *
     * @return CustomFacet
     */
    public function hydrateFacet(SimpleXMLElement $select)
    {
        $name = (string)$select->name;
        $label = (string)$select->display;
        $type = (string)$select->type;
        $select = (string)$select->select;

        $formFieldName = $this->getFormFieldName($name);

        $customFacet = new CustomFacet();
        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);

        if ($type === 'range-slider') {
            $mode = ProductAttributeFacet::MODE_RANGE_RESULT;
        } elseif ($select === 'single') {
            $mode = ProductAttributeFacet::MODE_RADIO_LIST_RESULT;
        } elseif ($select === 'multiple' || $select === 'multiselect') {
            $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
        } else {
            $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
        }

        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);

        $customFacet->setFacet($productAttributeFacet);

        return $customFacet;
    }

    /**
     * Keeps umlauts and regular characters. Anything else will be replaced by an underscore according to the PHP
     * documentation.
     *
     * @see http://php.net/manual/en/language.variables.external.php
     *
     * @param string $name
     *
     * @return string The escaped string or the original in case of an error.
     */
    public function getFormFieldName($name)
    {
        $escapedName = preg_replace(
            '/[^\xC3\x96|\xC3\x9C|\xC3\x9F|\xC3\xA4|\xC3\xB6|\xC3\xBC|\x00-\x7F]|[\.\s\x5B]/',
            '_',
            $name
        );

        // Reduces successive occurrences of an underscore to a single character.
        $escapedName = preg_replace('/_{2,}/', '_', $escapedName);

        // Fall back to the original name if it couldn't be escaped.
        return $escapedName ?: $name;
    }
}
