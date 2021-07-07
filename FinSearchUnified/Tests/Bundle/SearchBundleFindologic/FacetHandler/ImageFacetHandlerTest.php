<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FINDOLOGIC\GuzzleHttp\Handler\MockHandler;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Media as FilterMedia;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ImageFilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\VendorImageFilter;
use FinSearchUnified\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Message\Response as LegacyResponse;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Psr7\Response;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListItem;
use Shopware\Bundle\StoreFrontBundle\Struct\Media;
use Shopware\Components\HttpClient\GuzzleFactory;
use SimpleXMLElement;

class ImageFacetHandlerTest extends TestCase
{
    public function filterProvider()
    {
        return [
            'Filter with "select" type' => [ApiSelectDropdownFilter::class, false],
            'Filter with "label" type' => [ApiLabelTextFilter::class, false],
            'Filter with "image" type' => [ApiVendorImageFilter::class, true],
            'Filter with "range-slider" type' => [ApiRangeSliderFilter::class, false],
            'Filter with "color" type' => [ApiColorPickerFilter::class, false],
        ];
    }

    /**
     * @dataProvider filterProvider
     *
     * @param string $apiFilter
     * @param bool   $doesSupport
     */
    public function testSupportsFilter($apiFilter, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new $apiFilter(new SimpleXMLElement($data));
        $facetHandler = new ImageFacetHandler(Shopware()->Container()->get('guzzle_http_client_factory'));
        $result = $facetHandler->supportsFilter(Filter::getInstance($filter));

        static::assertSame($doesSupport, $result);
    }

    public function imageFilterProvider()
    {
        return [
            'Image filter with condition' => [
                'filterData' => [
                    ['name' => 'Red', 'image' => '', 'status' => 404],
                    ['name' => 'Green', 'image' => '', 'status' => 404],
                    ['name' => 'Zima Blue', 'image' => 'https://example.com/zima-blue.gif', 'status' => 200],
                    ['name' => 'Yellow', 'image' => 'https://example.com/yellow.gif', 'status' => 200],
                    ['name' => 'Purple', 'image' => 'https://example.com/purple.gif', 'status' => 404],
                    ['name' => 'Light Purple', 'image' => 'https://example.com/light-purple.gif', 'status' => 404],
                ],
                'condition' => new ProductAttributeCondition(
                    'vendor',
                    Operator::EQ,
                    ['Red', 'Zima Blue', 'Purple']
                ),
                'facetData' => [
                    ['id' => 'Red', 'active' => true, 'media' => null],
                    ['id' => 'Green', 'active' => false, 'media' => null],
                    ['id' => 'Zima Blue', 'active' => true, 'media' => 'https://example.com/zima-blue.gif'],
                    ['id' => 'Yellow', 'active' => false, 'media' => 'https://example.com/yellow.gif'],
                    ['id' => 'Purple', 'active' => true, 'media' => 'https://example.com/purple.gif'],
                    ['id' => 'Light Purple', 'active' => false, 'media' => 'https://example.com/light-purple.gif'],
                ],
            ],
            'Image filter without condition' => [
                'filterData' => [
                    ['name' => 'Red', 'image' => '', 'status' => 404],
                ],
                'condition' => null,
                'facetData' => [
                    ['id' => 'Red', 'active' => false, 'media' => null],
                ],
            ],
            'Image filter with condition but without filters' => [
                'filterData' => [],
                'condition' => new ProductAttributeCondition(
                    'vendor',
                    Operator::EQ,
                    ['Red', 'Zima Blue', 'Purple']
                ),
                'facetData' => [
                    ['id' => 'Red', 'active' => true, 'media' => null],
                    ['id' => 'Zima Blue', 'active' => true, 'media' => null],
                    ['id' => 'Purple', 'active' => true, 'media' => null],
                ],
            ],
        ];
    }

    /**
     * @dataProvider imageFilterProvider
     *
     * @param array $filterData
     * @param ConditionInterface|null $condition
     * @param array $facetData
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        array $filterData,
        ConditionInterface $condition = null,
        array $facetData = []
    ) {
        $facet = new ProductAttributeFacet(
            'vendor',
            ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
            'vendor',
            'Manufacturer'
        );

        $active = false;
        $criteria = new Criteria();

        if ($condition !== null) {
            $active = true;
            $criteria->addCondition($condition);
        }

        $filter = $this->generateFilter($filterData);

        $responses = [];

        foreach ($filterData as $filterDatum) {
            if ($filterDatum['image']) {
                $responses[] = class_exists(LegacyResponse::class) ?
                    new LegacyResponse($filterDatum['status']) :
                    new Response($filterDatum['status']);
            }
        }

        if (!class_exists(Mock::class)) {
            $mock = new MockHandler($responses);
            $handlerStack = HandlerStack::create($mock);

            $client = new Client(['handler' => $handlerStack]);
        } else {
            // Legacy Guzzle mocking
            $client = new Client();

            $mock = new Mock($responses);
            $client->getEmitter()->attach($mock);
        }

        $guzzleFactoryMock = $this->createMock(GuzzleFactory::class);
        $guzzleFactoryMock->method('createClient')->willReturn($client);

        $facetHandler = new ImageFacetHandler($guzzleFactoryMock, []);

        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $mediaListItems = [];

        foreach ($facetData as $facetDatum) {
            if ($facetDatum['media'] !== null) {
                $media = new Media();
                $media->setFile($facetDatum['media']);
            } else {
                $media = null;
            }

            $mediaItem = new MediaListItem($facetDatum['id'], $facetDatum['id'], $facetDatum['active'], $media);
            $mediaListItems[] = $mediaItem;
        }

        $facetResult = new MediaListFacetResult(
            'product_attribute_vendor',
            $active,
            'Manufacturer',
            $mediaListItems,
            'vendor'
        );

        $this->assertEquals($facetResult, $result);
    }

    /**
     * @param array $filterData
     *
     * @return VendorImageFilter
     */
    public function generateFilter(array $filterData)
    {
        $filter = new VendorImageFilter('vendor', 'Brand');

        foreach ($filterData as $key => $value) {
            $filterValue = new ImageFilterValue($value['name'], $value['name']);
            if ($value['image']) {
                $media = new FilterMedia($value['image']);
                $filterValue->setMedia($media);
            }
            $filter->addValue($filterValue);
        }

        return $filter;
    }
}
