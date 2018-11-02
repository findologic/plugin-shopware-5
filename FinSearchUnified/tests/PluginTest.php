<?php

namespace FinSearchUnified\tests;

use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Test\Plugin\TestCase;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCD0815'
        ],
    ];

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function tearDown()
    {
        // Reset articles data after each test execution
        $this->sResetArticles();
    }

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testCalculateGroupkey()
    {
        $shopkey = 'ABCD0815';
        $usergroup = 'at_rated';
        $hash = StaticHelper::calculateUsergroupHash($shopkey, $usergroup);
        $decrypted = StaticHelper::decryptUsergroupHash($shopkey, $hash);
        $this->assertEquals($usergroup, $decrypted);
    }

    /**
     * Data provider for the export test cases:
     * case 1: both products are active and both are returned
     * case 2: one product is active and one is inactive, so active product is returned
     * case 3: both products are inactive, so neither of them are returned in the export
     *
     * @return array
     */
    public function addDataProvider()
    {
        return [
            [[true, true], 2],
            [[true, false], 1],
            [[false, false], 0],
        ];
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider addDataProvider
     * @param array $isActive
     * @param int $expected
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function testArticleExport($isActive, $expected)
    {
        // Create two test articles with the provided data to test the export functionality
        $this->createTestProduct(1, $isActive[0]);
        $this->createTestProduct(2, $isActive[1]);

        $this->assertEquals($expected, $this->runExportAndReturnCount());
    }

    /**
     * @param int $number
     * @param bool $isActive
     * @return \Shopware\Models\Article\Article
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    private function createTestProduct($number, $isActive)
    {
        // Create temporary test product for the export cases
        $testArticle = [
            'name' => 'TestArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Test Supplier',
            'categories' => [
                ['id' => 3],
                ['id' => 5],
            ],
            'images' => [
                ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                ['link' => 'https://via.placeholder.com/300/09f/000.png'],
            ],
            'mainDetail' => [
                'number' => 'SW100' . $number,
                'active' => $isActive,
                'inStock' => 16,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ],
                ]
            ],
        ];
        $manger = new \Shopware\Components\Api\Manager();
        $resource = $manger->getResource('Article');
        $article = $resource->create($testArticle);

        // Assertion to make sure the test article is created properly
        $this->assertInstanceOf(\Shopware\Models\Article\Article::class, $article);

        return $article;
    }

    /**
     * Method to run the actual export functionality and parse the xml to return the
     * number of articles returned
     *
     * @return int
     */
    private function runExportAndReturnCount()
    {
        try {
            $shopKey = 'ABCD0815';
            $blController = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $blController->setShopKey($shopKey);
            $xmlDocument = $blController->getFindologicXml();

            // Parse the xml and return the count of the products exported
            $xml = new \SimpleXMLElement($xmlDocument);
            return (int)$xml->items->attributes()->count;
        } catch (\Exception $e) {
            // Simply log the exception message in the console
            echo "\nException: " . $e->getMessage();
        }

        return 0;
    }

    /**
     * Truncate all article related tables so we have a fresh database
     *
     * @throws \Zend_Db_Adapter_Exception
     */
    public function sResetArticles()
    {
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
}