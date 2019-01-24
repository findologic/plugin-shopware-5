<?php

use FinSearchUnified\Bundles\FindologicFacetGateway;
use FinSearchUnified\Bundles\ProductNumberSearch;
use FinSearchUnified\Helper\UrlBuilder;

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
                $manger = new \Shopware\Components\Api\Manager();
                $resource = $manger->getResource('Article');
                $resource->create($testArticle);
            } catch (\Exception $e) {
                echo sprintf("Exception: %s", $e->getMessage());
            }
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        $sql = '
            SET foreign_key_checks = 0;
			TRUNCATE s_articles;
			TRUNCATE s_filter_articles;
			TRUNCATE s_articles_attributes;
			TRUNCATE s_articles_avoid_customergroups;
			TRUNCATE s_articles_categories;
			TRUNCATE s_articles_details;
			TRUNCATE s_articles_downloads;
			TRUNCATE s_articles_downloads_attributes;
			TRUNCATE s_articles_esd;
			TRUNCATE s_articles_esd_attributes;
			TRUNCATE s_articles_esd_serials;
			TRUNCATE s_articles_img;
			TRUNCATE s_articles_img_attributes;
			TRUNCATE s_articles_information;
			TRUNCATE s_articles_information_attributes;
			TRUNCATE s_articles_notification;
            TRUNCATE s_articles_prices_attributes;
			TRUNCATE s_articles_prices;
			TRUNCATE s_articles_relationships;
			TRUNCATE s_articles_similar;
			TRUNCATE s_articles_translations;
			TRUNCATE s_article_configurator_dependencies;
			TRUNCATE s_article_configurator_groups;
			TRUNCATE s_article_configurator_options;
			TRUNCATE s_article_configurator_option_relations;
			TRUNCATE s_article_configurator_price_variations;
			TRUNCATE s_article_configurator_set_group_relations;
			TRUNCATE s_article_configurator_set_option_relations;
			TRUNCATE s_article_configurator_sets;
            TRUNCATE s_article_configurator_templates_attributes;
            TRUNCATE s_article_configurator_template_prices_attributes;
            TRUNCATE s_article_configurator_template_prices;
			TRUNCATE s_article_configurator_templates;
			TRUNCATE s_article_img_mapping_rules;
			TRUNCATE s_article_img_mappings;
        ';
        Shopware()->Db()->query($sql);
        try {
            // Follow-up: Truncate the tables that were not cleared in the first round
            Shopware()->Db()->query('TRUNCATE s_article_configurator_accessory_groups;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_accessory_articles;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_sets;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_set_group_relations;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_set_option_relations;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_templates;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_templates_attributes;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_template_prices;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_price_variations;');
            Shopware()->Db()->query('TRUNCATE s_articles_categories_ro;');
            Shopware()->Db()->exec(
                "
                        TRUNCATE s_articles_supplier;
                        TRUNCATE s_articles_supplier_attributes;
                        INSERT INTO s_articles_supplier (`id`, `name`) VALUES (1, 'Default');
                        INSERT INTO s_articles_supplier_attributes (`id`) VALUES (1);
                        UPDATE s_articles SET supplierID=1 WHERE 1;
                    "
            );
        } catch (\Exception $ignored) {
            // If table does not exist - resume, it might be just an old SW version
        }
    }

    protected function tearDown()
    {
        $kernel = Shopware()->Container()->get('kernel');
        $connection = Shopware()->Container()->get('db_connection');
        $db = Shopware()->Container()->get('db');
        $application = Shopware()->Container()->get('application');

        Shopware()->Container()->reset();

        Shopware()->Container()->set('kernel', $kernel);
        Shopware()->Container()->set('db_connection', $connection);
        Shopware()->Container()->set('db', $db);
        Shopware()->Container()->set('application', $application);

        /** @var $repository \Shopware\Models\Shop\Repository */
        $repository = Shopware()->Container()->get('models')->getRepository('Shopware\Models\Shop\Shop');

        $shop = $repository->getActiveDefault();
        $shop->registerResources();

        $_SERVER['HTTP_HOST'] = $shop->getHost();

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

        $session = $this->getMockBuilder(Enlight_Components_Session_Namespace::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->getMock();
        $session->expects($this->atLeastOnce())->method('offsetExists')->willReturnMap([
            'findologicDI' => true
        ]);
        $session->expects($this->atLeastOnce())->method('offsetGet')->willReturnMap([
            ['isSearchPage', true],
            ['isCategoryPage', false],
            ['findologicDI', false]
        ]);

        Shopware()->Container()->set('session', $session);

        $configArray = [
            ['ActivateFindologic', true],
            ['ActivateFindologicForCategoryPages', false],
            ['findologicDI', false],
            ['isSearchPage', true],
            ['isCategoryPage', false],
            ['ShopKey', '8D6CA2E49FB7CD09889CC0E2929F86B0'],
            ['host', Shopware()->Shop()->getHost()],
            ['basePath', Shopware()->Shop()->getHost() . Shopware()->Shop()->getBasePath()]
        ];
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet', 'setShop'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);
        $config->expects($this->atLeastOnce())
            ->method('setShop')
            ->willReturnSelf();
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

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
