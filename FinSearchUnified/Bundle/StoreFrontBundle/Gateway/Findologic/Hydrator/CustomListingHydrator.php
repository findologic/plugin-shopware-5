<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Components\ConfigLoader;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use SimpleXMLElement;
use Zend_Cache_Exception;

class CustomListingHydrator
{
    private $configLoader;

    public function __construct(ConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

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

        if ($type === 'range-slider') {
            $mode = ProductAttributeFacet::MODE_RANGE_RESULT;
        } elseif ($select === 'single') {
            $mode = ProductAttributeFacet::MODE_RADIO_LIST_RESULT;
        } elseif ($select === 'multiple' || $select === 'multiselect') {
            $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
        } else {
            $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
        }

        return $this->createCustomFacet($name, $mode, $label);
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

    /**
     * @param string $name
     * @param string $mode
     * @param string $label
     *
     * @return CustomFacet
     */
    private function createCustomFacet($name, $mode, $label)
    {
        $formFieldName = $this->getFormFieldName($name);

        $customFacet = new CustomFacet();
        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);

        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);
        $customFacet->setFacet($productAttributeFacet);

        return $customFacet;
    }

    /**
     * @return CustomFacet
     * @throws Zend_Cache_Exception
     */
    public function hydrateDefaultCategoryFacet()
    {
        $smartSuggestion = $this->configLoader->getSmartSuggestBlocks();
        $label = $smartSuggestion['cat'];
        $name = 'cat';
        $mode = ProductAttributeFacet::MODE_RADIO_LIST_RESULT;

        return $this->createCustomFacet($name, $mode, $label);
    }

    /**
     * @return CustomFacet
     * @throws Zend_Cache_Exception
     */
    public function hydrateDefaultVendorFacet()
    {
        $smartSuggestion = $this->configLoader->getSmartSuggestBlocks();
        $label = $smartSuggestion['vendor'];
        $name = 'vendor';
        $mode = ProductAttributeFacet::MODE_RADIO_LIST_RESULT;

        return $this->createCustomFacet($name, $mode, $label);
    }
}
