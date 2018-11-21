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
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProvider()
    {
        return [
            "2 active articles" => [[true, true], 2, "Two articles were expected but %d were returned"],
            "1 active and 1 inactive article" => [
                [true, false],
                1,
                "Only one article was expected but %d were returned "
            ],
            "2 inactive articles" => [
                [false, false],
                0,
                "No articles were expected but %d were returned"
            ],
        ];
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider articleProvider
     * @param array $isActive
     * @param int $expected
     * @param string $errorMessage
     */
    public function testArticleExport($isActive, $expected, $errorMessage)
    {
        // Create articles with the provided data to test the export functionality
        for ($i = 0; $i < count($isActive); $i++) {
            $this->createTestProduct($i, $isActive[$i]);
        }
        $actual = $this->runExportAndReturnCount();
        $this->assertEquals($expected, $actual, sprintf($errorMessage, $actual));
    }

    /**
     * Method to create test products for the export
     *
     * @param int $number
     * @param bool $isActive
     * @return \Shopware\Models\Article\Article|null
     */
    private function createTestProduct($number, $isActive)
    {
        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => $isActive,
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
                'number' => 'FINDOLOGIC' . $number,
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

        try {
            $manger = new \Shopware\Components\Api\Manager();
            $resource = $manger->getResource('Article');
            $article = $resource->create($testArticle);
            return $article;
        } catch (\Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return null;
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
            /** @var \FinSearchUnified\ShopwareProcess $blController */
            $blController = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $blController->setShopKey($shopKey);
            $xmlDocument = $blController->getFindologicXml();

            // Parse the xml and return the count of the products exported
            $xml = new \SimpleXMLElement($xmlDocument);
            return (int)$xml->items->attributes()->count;
        } catch (\Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return 0;
    }

    /**
     * Reset articles data after each test
     */
    protected function tearDown()
    {
        $this->sResetArticles();
    }

    /**
     * Truncate all article related tables so we have a fresh database
     *
     * @throws \Zend_Db_Adapter_Exception
     */
    private function sResetArticles()
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
