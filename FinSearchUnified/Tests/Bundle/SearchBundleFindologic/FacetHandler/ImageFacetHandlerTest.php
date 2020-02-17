<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
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
            'Filter with "select" type' => ['select', true, false],
            'Filter with "label" type' => ['label', true, false],
            'Filter with "range-slider" type' => ['range-slider', true, false],
            'Filter with "color" type without image' => ['color', false, false],
            'Filter with "color" type with image' => ['color', true, true],
            'Filter with "image" type' => ['image', true, true],
        ];
    }

    /**
     * @dataProvider filterProvider
     *
     * @param string $type
     * @param bool $hasImage
     * @param bool $doesSupport
     */
    public function testSupportsFilter($type, $hasImage, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', 'vendor');
        $filter->addChild('type', $type);

        if ($type === 'color' && $hasImage) {
            $filter->addChild('items')->addChild('item')->addChild('image', 'image');
        }

        $facetHandler = new ImageFacetHandler(Shopware()->Container()->get('guzzle_http_client_factory'), []);
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
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
                'condition' =>
                    new ProductAttributeCondition(
                        'vendor',
                        Operator::EQ,
                        ['Red', 'Zima Blue', 'Purple']
                    ),
                'facetData' =>
                    [
                        ['id' => 'Red', 'active' => true, 'media' => null],
                        ['id' => 'Green', 'active' => false, 'media' => null],
                        ['id' => 'Zima Blue', 'active' => true, 'media' => 'https://example.com/zima-blue.gif'],
                        ['id' => 'Yellow', 'active' => false, 'media' => 'https://example.com/yellow.gif'],
                        ['id' => 'Purple', 'active' => true, 'media' => null],
                        ['id' => 'Light Purple', 'active' => false, 'media' => null],
                    ]
            ],
            'Image filter without condition' => [
                'filterData' => [
                    ['name' => 'Red', 'image' => '', 'status' => 404],
                ],
                'condition' => null,
                'facetData' =>
                    [
                        ['id' => 'Red', 'active' => false, 'media' => null]
                    ]
            ],
            'Image filter with condition but without filters' => [
                'filterData' => [],
                'condition' =>
                    new ProductAttributeCondition(
                        'vendor',
                        Operator::EQ,
                        ['Red', 'Zima Blue', 'Purple']
                    ),
                'facetData' =>
                    [
                        ['id' => 'Red', 'active' => true, 'media' => null],
                        ['id' => 'Zima Blue', 'active' => true, 'media' => null],
                        ['id' => 'Purple', 'active' => true, 'media' => null]
                    ]
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
                $responses[] = new Response($filterDatum['status']);
            }
        }

        $client = new Client();

        // Create a mock subscriber and queue responses.
        $mock = new Mock($responses);

        // Add the mock subscriber to the client.
        $client->getEmitter()->attach($mock);

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
     * @return SimpleXMLElement
     */
    public function generateFilter(array $filterData)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', 'vendor');
        $filter->addChild('display', 'Manufacturer');
        $filter->addChild('select', 'single');
        $filter->addChild('type', 'image');

        $items = $filter->addChild('items');
        // Loop through the data to generate filter xml
        foreach ($filterData as $key => $value) {
            $item = $items->addChild('item');
            $item->addChild('name', $value['name']);
            $item->addChild('image', $value['image']);
        }

        return $filter;
    }
}
