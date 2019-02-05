<?php

use FinSearchUnified\Bundles\ProductNumberSearch;
use FinSearchUnified\Helper\UrlBuilder;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactory;
use Shopware\Bundle\SearchBundle\ProductSearch;

class FinSearchUnified_Tests_Controllers_Frontend_SearchTest extends Enlight_Components_Test_Plugin_TestCase
{
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
    
    public function setUp()
    {
        parent::setUp();

        $this->setConfig('ActivateFindologic', true);
        $this->setConfig('ActivateFindologicForCategoryPages', false);
        $this->setConfig('ShopKey', '0000000000000000ZZZZZZZZZZZZZZZZ');

        Shopware()->Container()->reset('fin_search_unified.subscriber.frontend');
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Session()->offsetUnset('findologicDI');

        Shopware()->Container()->reset('shopware_search.store_front_criteria_factory');
        Shopware()->Container()->load('shopware_search.store_front_criteria_factory');

        Shopware()->Container()->reset('shopware_search.product_search');
        Shopware()->Container()->load('shopware_search.product_search');
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

        Shopware()->Session()->offsetSet('findologicDI', false);

        $criteria = new Criteria();
        $criteria->addBaseCondition(new SearchTermCondition('blubbergurke'));
        $storeFrontCriteriaFactoryMock = $this->getMockBuilder(StoreFrontCriteriaFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['createSearchCriteria'])
            ->getMock();
        $storeFrontCriteriaFactoryMock->expects($this->once())->method('createSearchCriteria')->willReturn($criteria);

        Shopware()->Container()->set(
            'shopware_search.store_front_criteria_factory',
            $storeFrontCriteriaFactoryMock
        );

        $urlBuilderMock = $this->getMockBuilder(UrlBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'setCustomerGroup',
                'buildQueryUrlAndGetResponse'
            ])
            ->getMock();

        if ($originalQuery !== null) {
            $urlBuilderMock->expects($this->once())->method('setCustomerGroup');
            $urlBuilderMock->expects($this->once())->method('buildQueryUrlAndGetResponse')->willReturn(
                new Zend_Http_Response(200, [], $xmlResponse->asXML())
            );
        }

        $productNumberSearch = new ProductNumberSearch(
            Shopware()->Container()->get('shopware_search.product_number_search'),
            $urlBuilderMock
        );

        $productSearch = new ProductSearch(
            Shopware()->Container()->get('shopware_storefront.list_product_service'),
            $productNumberSearch
        );

        Shopware()->Container()->set('shopware_search.product_search', $productSearch);

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
            // Get shop locale to check for text in relevant language
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

    /**
     * Allows to set a Shopware config
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function setConfig($name, $value)
    {
        Shopware()->Container()->get('config_writer')->save($name, $value);
        Shopware()->Container()->get('cache')->clean();
        Shopware()->Container()->get('config')->setShop(Shopware()->Shop());
    }
}
