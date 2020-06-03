<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\CategoryFilter as ApiCategoryFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Filter as ApiFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\CategoryItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\ColorItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\DefaultItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\RangeSliderItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\Item\VendorImageItem;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\FilterValueImageHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\CategoryFilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ColorFilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\FilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ImageFilterValue;
use GuzzleHttp\Client;
use InvalidArgumentException;

abstract class Filter extends BaseFilter
{
    /** @var FilterValue[] */
    protected $values;

    /**
     * Builds a new filter instance. May return null for unsupported filter types. Throws an exception for unknown
     * filter types.
     *
     * @param ApiFilter $filter
     * @param Client|null $client Used to fetch images from vendor image or color filters. If not set a new client
     * instance will be created internally.
     *
     * @return Filter|null
     */
    public static function getInstance(ApiFilter $filter, Client $client = null)
    {
        switch (true) {
            case $filter instanceof ApiLabelTextFilter:
                return static::handleLabelTextFilter($filter);
            case $filter instanceof ApiSelectDropdownFilter:
                return static::handleSelectDropdownFilter($filter);
            case $filter instanceof ApiRangeSliderFilter:
                return static::handleRangeSliderFilter($filter);
            case $filter instanceof ApiColorPickerFilter:
                return static::handleColorPickerFilter($filter, $client);
            case $filter instanceof ApiVendorImageFilter:
                return static::handleVendorImageFilter($filter, $client);
            case $filter instanceof ApiCategoryFilter:
                return static::handleCategoryFilter($filter);
            default:
                throw new InvalidArgumentException('The submitted filter is unknown.');
        }
    }

    private static function handleLabelTextFilter(ApiLabelTextFilter $filter)
    {
        $customFilter = new LabelTextFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleSelectDropdownFilter(ApiSelectDropdownFilter $filter)
    {
        $customFilter = new SelectDropdownFilter($filter->getName(), $filter->getDisplay());

        /** @var DefaultItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleRangeSliderFilter(ApiRangeSliderFilter $filter)
    {
        $customFilter = new RangeSliderFilter($filter->getName(), $filter->getDisplay());
        $attributes = $filter->getAttributes();

        if ($attributes) {
            $unit = $attributes->getUnit();
            if ($unit !== null) {
                $customFilter->setUnit($unit);
            }
            $customFilter->setActiveMin($attributes->getSelectedRange()->getMin());
            $customFilter->setActiveMax($attributes->getSelectedRange()->getMax());
            $customFilter->setMin($attributes->getTotalRange()->getMin());
            $customFilter->setMax($attributes->getTotalRange()->getMax());
        }

        /** @var RangeSliderItem $item */
        foreach ($filter->getItems() as $item) {
            $customFilter->addValue(new FilterValue($item->getName(), $item->getName()));
        }

        return $customFilter;
    }

    private static function handleColorPickerFilter(ApiColorPickerFilter $filter)
    {
        $customFilter = new ColorPickerFilter($filter->getName(), $filter->getDisplay());

        /** @var ColorItem $item */
        foreach ($filter->getItems() as $item) {
            $filterValue = new ColorFilterValue($item->getName(), $item->getName());
            $filterValue->setColorHexCode($item->getColor());

            $media = new Media($item->getImage());
            $filterValue->setMedia($media);

            $customFilter->addValue($filterValue);
        }

        return $customFilter;
    }

    private static function handleVendorImageFilter(ApiVendorImageFilter $filter)
    {
        $customFilter = new VendorImageFilter($filter->getName(), $filter->getDisplay());

        /** @var VendorImageItem $item */
        foreach ($filter->getItems() as $item) {
            $filterValue = new ImageFilterValue($item->getName(), $item->getName());
            $media = new Media($item->getImage());
            $filterValue->setMedia($media);
            $customFilter->addValue($filterValue);
        }

        return $customFilter;
    }

    private static function handleCategoryFilter(ApiCategoryFilter $filter)
    {
        $customFilter = new CategoryFilter($filter->getName(), $filter->getDisplay());

        /** @var CategoryItem $item */
        foreach ($filter->getItems() as $item) {
            $filterValue = new CategoryFilterValue($item->getName(), $item->getName());
            $filterValue->setSelected($item->isSelected());
            $filterValue->setFrequency($item->getFrequency());
            self::parseSubFilters($filterValue, $item->getItems());

            $customFilter->addValue($filterValue);
        }

        return $customFilter;
    }

    /**
     * @param CategoryItem[] $items
     */
    private static function parseSubFilters(CategoryFilterValue $filterValue, array $items)
    {
        foreach ($items as $item) {
            $filter = new CategoryFilterValue($item->getName(), $item->getName());
            $filter->setSelected($item->isSelected());
            $filter->setFrequency($item->getFrequency());
            self::parseSubFilters($filter, $item->getItems());

            $filterValue->addValue($filter);
        }
    }

    public function addValue(FilterValue $filterValue)
    {
        $this->values[] = $filterValue;

        return $this;
    }
}
