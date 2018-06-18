<?php
/**
 * Created by PhpStorm.
 * User: wege
 * Date: 16.05.2018
 * Time: 09:44.
 */

namespace FinSearchAPI\Bundles\FacetResult;

class ColorPickerFacetResult extends \Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult
{
    protected $colorCode;

    public function __construct($facetName, $active, $label, array $values, $fieldName, $attributes = [], $template = 'frontend/listing/filter/facet-color-list.tpl')
    {
        parent::__construct($facetName, $active, $label, $values, $fieldName, $attributes, $template);
    }
}
