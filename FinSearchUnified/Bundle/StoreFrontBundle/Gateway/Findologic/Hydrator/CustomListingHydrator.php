<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
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

    /**
     * @param string $label
     * @param string $name
     * @param string $type
     * @param string $filter
     *
     * @return CustomFacet
     */
    private function createFindologicFacet($label, $name, $type, $filter)
    {
        $formFieldName = StaticHelper::escapeFilterName($name);

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
}
