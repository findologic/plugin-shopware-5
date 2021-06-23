<?php

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilderFactory;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\OldPhpUnitVersionAware;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductSearch;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactory;
use Shopware\Bundle\SearchBundleDBAL;
use Shopware\Components\Api\Manager;

class FinSearchUnified_Tests_Controllers_Frontend_SearchTest extends Enlight_Components_Test_Plugin_TestCase
{
    use OldPhpUnitVersionAware;

    public static function setUpBeforeClass(): void
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
                $manager = new Manager();
                $resource = $manager->getResource('Article');
                $resource->create($testArticle);
            } catch (Exception $e) {
                echo sprintf("Exception: %s", $e->getMessage());
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        Utility::sResetArticles();
    }

    public function setUp(): void
    {
        parent::setUp();

        Utility::setConfig('ActivateFindologic', true);
        Utility::setConfig('ActivateFindologicForCategoryPages', false);
        Utility::setConfig('ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Shopware()->Session()->offsetUnset('findologicDI');
        Shopware()->Session()->offsetUnset('isSearchPage');
        Shopware()->Session()->offsetUnset('isCategoryPage');

        Shopware()->Container()->reset('shopware_search.store_front_criteria_factory');
        Shopware()->Container()->load('shopware_search.store_front_criteria_factory');

        Shopware()->Container()->reset('shopware_search.product_search');
        Shopware()->Container()->load('shopware_search.product_search');

        Shopware()->Container()->reset('fin_search_unified.subscriber.frontend');
        Shopware()->Container()->load('fin_search_unified.subscriber.frontend');
        // Explicitly reset this super global since it might influence unrelated tests.
        $_GET = [];
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
            ]
        ];
    }

    /**
     * @dataProvider findologicResponseProvider
     */
    public function testSmartDidYouMeanSuggestionsAreShown(
        ?string $didYouMeanQuery,
        ?string $originalQuery,
        ?string $queryString,
        ?string $queryStringType,
        array $expectedText,
        string $expectedLink
    ): void {
        $response = $this->buildSmartDidYouMeanXmlResponse(
            $didYouMeanQuery,
            $originalQuery,
            $queryString,
            $queryStringType
        );

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);

        $criteria = new Criteria();

        // Method may not exist for Shopware 5.2.x
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }

        $this->Request()->setParam('sSearch', 'blubbergurke');

        $criteria->addBaseCondition(new SearchTermCondition('blubbergurke'));
        $storeFrontCriteriaFactoryMock = $this->getMockBuilder(StoreFrontCriteriaFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $storeFrontCriteriaFactoryMock->expects($this->any())
            ->method('createSearchCriteria')
            ->willReturn($criteria);

        Shopware()->Container()->set('shopware_search.store_front_criteria_factory', $storeFrontCriteriaFactoryMock);

        $mockedQuery = $this->getMockBuilder(SearchQueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockedQuery->expects($this->any())
            ->method('execute')
            ->willReturn($response);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        /** @var QueryBuilderFactory|MockObject $mockQueryBuilderFactory */
        $mockQueryBuilderFactory = $this->createMock(QueryBuilderFactory::class);

        $mockQueryBuilderFactory->expects($this->once())
            ->method('createProductQuery')
            ->with($criteria)
            ->willReturn($mockedQuery);

        /** @var SearchBundleDBAL\ProductNumberSearch|MockObject $originalService */
        $originalService = $this->createMock(SearchBundleDBAL\ProductNumberSearch::class);
        $originalService->expects($this->never())->method('search');

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQueryBuilderFactory,
            Shopware()->Container()->get('cache')
        );

        $productSearch = new ProductSearch(
            Shopware()->Container()->get('shopware_storefront.list_product_service'),
            $productNumberSearch
        );

        Shopware()->Container()->set('shopware_search.product_search', $productSearch);

        $this->dispatch('/search?sSearch=blubbergurke');
    }

    private function buildSmartDidYouMeanXmlResponse(
        ?string $didYouMeanQuery,
        ?string $originalQuery,
        ?string $queryString,
        ?string $queryStringType
    ): Xml21Response {
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

        $filters = $xmlResponse->addChild('filters');
        $filters->addChild('filter');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        $xmlResponse->servers->frontend = 'martell.frontend.findologic.com';
        $xmlResponse->servers->backend = 'hydra.backend.findologic.com';

        return new Xml21Response($xmlResponse->asXML());
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
     * @throws Exception
     * @throws Enlight_Exception
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

        $filters = $xmlResponse->addChild('filters');
        $filters->addChild('filter');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        $xmlResponse->servers->frontend = 'martell.frontend.findologic.com';
        $xmlResponse->servers->backend = 'hydra.backend.findologic.com';

        $response = new Xml21Response($xmlResponse->asXML());

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);


        $criteria = new Criteria();

        // Method may not exist for Shopware 5.2.x
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }

        $this->Request()->setParam('sSearch', 'blubbergurke');

        $criteria->addBaseCondition(new SearchTermCondition('blubbergurke'));
        $storeFrontCriteriaFactoryMock = $this->getMockBuilder(StoreFrontCriteriaFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $storeFrontCriteriaFactoryMock->expects($this->once())->method('createSearchCriteria')->willReturn($criteria);

        Shopware()->Container()->set('shopware_search.store_front_criteria_factory', $storeFrontCriteriaFactoryMock);

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockedQuery->expects($this->once())->method('execute')->willReturn($response);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($this->once())
            ->method('createProductQuery')
            ->willReturn($mockedQuery);

        /** @var ProductNumberSearch|MockObject $originalService */
        $originalService = $this->getMockBuilder(SearchBundleDBAL\ProductNumberSearch::class)
            ->disableOriginalConstructor()
            ->setMethods(['search'])
            ->getMock();
        $originalService->expects($this->never())->method('search');

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            Shopware()->Container()->get('cache')
        );

        $productSearch = new ProductSearch(
            Shopware()->Container()->get('shopware_storefront.list_product_service'),
            $productNumberSearch
        );

        Shopware()->Container()->set('shopware_search.product_search', $productSearch);

        $this->dispatch('/search?sSearch=blubbergurke');

        if (empty($expectedText)) {
            $this->assertStringNotContainsString(
                '<p id="fl-smart-did-you-mean" class="search--headline">',
                $this->Response()->getBody(),
                'Expected smart-did-you-mean tags to NOT be rendered'
            );
        } else {
            $this->assertStringContainsString(
                '<p id="fl-smart-did-you-mean" class="search--headline">',
                $this->Response()->getBody(),
                'Expected smart-did-you-mean tags to be visible'
            );
            // Get shop locale to check for text in relevant language
            $locale = Shopware()->Shop()->getLocale()->getLocale();
            $text = isset($expectedText[$locale]) ? $expectedText[$locale] : $expectedText['en_GB'];
            $this->assertStringContainsString(
                $text,
                $this->Response()->getBody(),
                'Incorrect text was displayed'
            );
            if ($expectedLink !== '') {
                $this->assertStringContainsString(
                    $expectedLink,
                    $this->Response()->getBody(),
                    'Incorrect target link was generated'
                );
            }
        }
    }
}
