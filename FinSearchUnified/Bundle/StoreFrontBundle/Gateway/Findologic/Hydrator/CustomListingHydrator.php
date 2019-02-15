<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use SimpleXMLElement;

class CustomListingHydrator
{
    /**
     * @param SimpleXMLElement $filter
     *
     * @return CustomFacet
     */
    public function hydrateFacet(SimpleXMLElement $filter)
    {
        $name = (string)$filter->name;
        $label = (string)$filter->display;
        $type = (string)$filter->type;
        $filter = (string)$filter->select;

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
