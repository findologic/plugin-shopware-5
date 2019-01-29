<?php

use FinSearchUnified\Bundles\FindologicFacetGateway;
use FinSearchUnified\Bundles\ProductNumberSearch;
use FinSearchUnified\Helper\UrlBuilder;
use FinSearchUnified\Tests\Helper\Utility;

class FinSearchUnified_Tests_Controllers_Frontend_SearchTest extends Enlight_Components_Test_Plugin_TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
            'ActivateFindologicForCategoryPages' => false
        ],
    ];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        for ($i = 0; $i < 5; $i++) {
            $id = uniqid();
            $testArticle = [
                'name' => 'FindologicArticle' . $id,
                'active' => true,
                'tax' => 19,
                'supplier' => 'Findologic',
                'categories' => [
                    ['id' => 3],
                    ['id' => 5],
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/300/09f/000.png'],
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC' . $id,
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 53.84,
                        ],
                    ]
                ],
            ];

            try {
                $manager = new \Shopware\Components\Api\Manager();
                $resource = $manager->getResource('Article');
                $resource->create($testArticle);
            } catch (\Exception $e) {
                echo sprintf("Exception: %s", $e->getMessage());
            }
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        Utility::sResetArticles();
    }

    protected function tearDown()
    {
        Utility::resetContainer();
        parent::tearDown();
    }

    public function findologicResponseProvider()
    {
        return [
            'No smart-did-you-mean tags present' => [
                null,
                null,
                null,
                null,
                [],
                ''
            ],
            'Type is did-you-mean' => [
                'didYouMeanQuery',
                'originalQuery',
                'queryString',
                null,
                ['en_GB' => 'Did you mean', 'de_DE' => 'Meinten Sie'],
                'sSearch=didYouMeanQuery&forceOriginalQuery=1'
            ],
            'Type is improved' => [
                null,
                'originalQuery',
                'queryString',
                'improved',
                ['en_GB' => 'Search instead', 'de_DE' => 'Alternativ nach'],
                'sSearch=originalQuery&forceOriginalQuery=1'
            ],
            'Type is corrected' => [
                null,
                'originalQuery',
                'queryString',
                'corrected',
                ['en_GB' => 'No results for', 'de_DE' => 'Keine Ergebnisse für'],
                ''
            ],
            'Type is forced' => [
                null,
                'originalQuery',
                'queryString',
                'forced',
                [],
                ''
            ],
        ];
    }

    /**
     * @dataProvider findologicResponseProvider
     *
     * @param string $didYouMeanQuery
     * @param string $originalQuery
     * @param string $queryString
     * @param string $queryStringType
     * @param array $expectedText
     * @param string $expectedLink
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testSmartDidYouMeanSuggestionsAreDisplayed(
        $didYouMeanQuery,
        $originalQuery,
        $queryString,
        $queryStringType,
        array $expectedText,
        $expectedLink
    ) {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);
        $query = $xmlResponse->addChild('query');

        if ($queryString !== null) {
            $queryString = $query->addChild('queryString', $queryString);

            if ($queryStringType !== null) {
                $queryString->addAttribute('type', $queryStringType);
            }
        }
        if ($didYouMeanQuery !== null) {
            $query->addChild('didYouMeanQuery', $didYouMeanQuery);
        }
        if ($originalQuery !== null) {
            $query->addChild('originalQuery', $originalQuery);
        }

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        $httpResponse = new Zend_Http_Response(
            200,
            [],
            '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>'
        );
        $urlBuilderMock = $this->getMockBuilder(UrlBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'buildCompleteFilterList',
                'buildCategoryUrlAndGetResponse',
                'setCustomerGroup',
                'buildQueryUrlAndGetResponse'
            ])
            ->getMock();
        if ($originalQuery !== null) {
            $urlBuilderMock->expects($this->exactly(1))->method('buildCompleteFilterList')->willReturn($httpResponse);
            $urlBuilderMock->expects($this->never())->method('buildCategoryUrlAndGetResponse');
            $urlBuilderMock->expects($this->exactly(2))->method('setCustomerGroup');
            $urlBuilderMock->expects($this->once())->method('buildQueryUrlAndGetResponse')->willReturn(
                new Zend_Http_Response(200, [], $xmlResponse->asXML())
            );
        }

        $facetGateway = new FindologicFacetGateway(
            Shopware()->Container()->get('shopware_storefront.custom_facet_gateway'),
            $urlBuilderMock
        );

        Shopware()->Container()->set('FinSearchUnified.findologic_facet_gateway', $facetGateway);

        $productNumberSearch = new ProductNumberSearch(
            Shopware()->Container()->get('shopware_search.product_number_search'),
            $urlBuilderMock
        );

        Shopware()->Container()->set('fin_search_unified.product_number_search', $productNumberSearch);

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);

        $response = $this->dispatch('/search?sSearch=blubbergurke');

        if (empty($expectedText)) {
            $this->assertNotContains(
                '<p id="fl-smart-did-you-mean">',
                $response->getBody(),
                'Expected smart-did-you-mean tags to NOT be rendered'
            );
        } else {
            $this->assertContains(
                '<p id="fl-smart-did-you-mean">',
                $response->getBody(),
                'Expected smart-did-you-mean tags to be visible'
            );
            $locale = Shopware()->Shop()->getLocale()->getLocale();
            $text = isset($expectedText[$locale]) ? $expectedText[$locale] : $expectedText['en_GB'];
            $this->assertContains(
                $text,
                $response->getBody(),
                'Incorrect text was displayed'
            );
            if ($expectedLink !== '') {
                $this->assertContains(
                    $expectedLink,
                    $response->getBody(),
                    'Incorrect target link was generated'
                );
            }
        }
    }
}
