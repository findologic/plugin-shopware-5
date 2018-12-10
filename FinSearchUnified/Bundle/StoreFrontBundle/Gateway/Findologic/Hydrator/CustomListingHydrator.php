<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Models\Search\CustomFacet;
use SimpleXMLElement;

class CustomListingHydrator
{
    /**
     * @param SimpleXMLElement $xmlResponse
     *
     * @return array
     */
    public function hydrateFacet(SimpleXMLElement $xmlResponse)
    {
        $facets = [];

        foreach ($xmlResponse->filters->filter as $filter) {
            $facets[] = $this->createFindologicFacet(
                (string)$filter->display,
                (string)$filter->name,
                (string)$filter->type,
                (string)$filter->select
            );
        }

        return $facets;
    }

    private function createFindologicFacet($label, $name, $type, $filter)
    {
        $formFieldName = self::escapeFilterName($name);

        switch ($type) {
            case 'label':
                if ($filter === 'single') {
                    $mode = ProductAttributeFacet::MODE_RADIO_LIST_RESULT;
                } else {
                    $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
                }
                break;
            case 'range-slider':
                $mode = ProductAttributeFacet::MODE_RANGE_RESULT;
                break;
            default:
                $mode = ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
                break;
        }

        $customFacet = new CustomFacet();
        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);

        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);
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
    public static function escapeFilterName($name)
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
