<?php
/**
 * Created by PhpStorm.
 * User: wege
 * Date: 16.05.2018
 * Time: 09:44
 */

namespace findologicDI\Bundles\FacetResult;

class ColorPickerFacetResult extends \Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult {

	protected $colorCode;

	public function __construct( string $facetName, bool $active, string $label, array $values, string $fieldName, $attributes = [], ?string $template = 'frontend/listing/filter/facet-color-list.tpl' ) {
		parent::__construct( $facetName, $active, $label, $values, $fieldName, $attributes, $template );
	}
}