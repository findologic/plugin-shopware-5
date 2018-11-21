<?php

namespace FinSearchUnified\Bundles\FacetResult;

class ColorPickerFacetResult extends \Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult
{
    protected $colorCode;

    public function __construct(
        $facetName,
        $active,
        $label,
        array $values,
        $fieldName,
        $attributes = [],
        $template = 'frontend/listing/filter/facet-color-list.tpl'
    ) {
        parent::__construct($facetName, $active, $label, $values, $fieldName, $attributes, $template);
    }
}
