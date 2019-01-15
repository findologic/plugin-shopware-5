<?php

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

    public function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('fin_search_unified.product_number_search');
        Shopware()->Container()->reset('FinSearchUnified.findologic_facet_gateway');
        Shopware()->Container()->reset('config');
        Shopware()->Container()->reset('session');
        Shopware()->Container()->reset('front');

        Shopware()->Container()->load('config');
        Shopware()->Container()->load('session');
        Shopware()->Container()->load('front');
        Shopware()->Container()->load('FinSearchUnified.findologic_facet_gateway');
        Shopware()->Container()->load('fin_search_unified.product_number_search');
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

    public function findologicResponseProvider()
    {
        return [
            'No smart-did-you-mean tags present' => [
                false,
                null,
                null,
                null,
                null
            ],
            'Type is did-you-mean' => [
                true,
                'didYouMeanQuery',
                'originalQuery',
                'queryString',
                null
            ],
            'Type is improved' => [
                true,
                null,
                'originalQuery',
                'queryString',
                'improved'
            ],
            'Type is corrected' => [
                true,
                null,
                'originalQuery',
                'queryString',
                'corrected'
            ],
            'Type is forced' => [
                true,
                null,
                'originalQuery',
                'queryString',
                'forced'
            ],
        ];
    }

    /**
     * @dataProvider findologicResponseProvider
     *
     * @param bool $activateFindologic
     * @param string $didYouMeanQuery
     * @param string $originalQuery
     * @param string $queryString
     * @param string $queryStringType
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testFindologicSearchResponse(
        $activateFindologic,
        $didYouMeanQuery,
        $originalQuery,
        $queryString,
        $queryStringType
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

        $configArray = [
            ['ActivateFindologic', $activateFindologic],
            ['ActivateFindologicForCategoryPages', false],
            ['findologicDI', false],
            ['ShopKey', '8D6CA2E49FB7CD09889CC0E2929F86B0'],
            ['host', Shopware()->Shop()->getHost()],
            ['basePath', Shopware()->Shop()->getHost() . Shopware()->Shop()->getBasePath()]
        ];

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
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

        $sessionArray = [
            ['isSearchPage', true],
            ['isCategoryPage', false]
        ];
        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        $httpResponse = new Zend_Http_Response(200, [], $xmlResponse->asXML());

        $httpClient = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();

        if ($activateFindologic) {
            $httpClient->expects($this->exactly(4))
                ->method('request')
                ->willReturnOnConsecutiveCalls(
                    new Zend_Http_Response(200, [], 'alive'),
                    $httpResponse,
                    new Zend_Http_Response(200, [], 'alive'),
                    $httpResponse
                );

            $productNumberSearch = new \FinSearchUnified\Bundles\ProductNumberSearch(
                Shopware()->Container()->get('fin_search_unified.product_number_search'),
                $httpClient
            );

            Shopware()->Container()->set('fin_search_unified.product_number_search', $productNumberSearch);

            $facetGateway = new \FinSearchUnified\Bundles\FindologicFacetGateway(
                Shopware()->Container()->get('FinSearchUnified.findologic_facet_gateway'),
                $httpClient
            );

            Shopware()->Container()->set('FinSearchUnified.findologic_facet_gateway', $facetGateway);
        }

        $this->Request()->setMethod('GET');

        $this->dispatch(sprintf('/search?sSearch=%s', $originalQuery));

        \FinSearchUnified\Helper\StaticHelper::setSmartDidYouMean($xmlResponse);

        $body = $this->View()->render();

        if (!$activateFindologic) {
            $this->assertNotContains(
                '<p id="fl-smart-did-you-mean">',
                $body,
                'Expected smart-did-you-mean-tags to not be rendered'
            );
        } else {
            $this->assertContains(
                '<p id="fl-smart-did-you-mean">',
                $body,
                'Expected smart-did-you-mean tags to be visible'
            );
        }
    }
}
