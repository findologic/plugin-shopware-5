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

        // reset articles data upon starting of test
        $this->sResetArticles();

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
     * @param $number
     * @param $isActive
     * @return \Shopware\Models\Article\Article
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    private function createTestProduct($number, $isActive)
    {
        // create temporary test product for the export cases
        $testArticle = array(
            'name' => 'TestArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Test Supplier',

            'categories' => array(
                array('id' => 3),
                array('id' => 5),
            ),
            'images' => array(
                array('link' => 'https://via.placeholder.com/300/F00/fff.png'),
                array('link' => 'https://via.placeholder.com/300/09f/000.png'),
            ),

            'mainDetail' => array(
                'number' => 'SW100' . $number,
                'active' => $isActive,
                'inStock' => 16,
                'prices' => array(
                    array(
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ),
                )
            ),
        );
        $manger = new \Shopware\Components\Api\Manager();
        $resource = $manger->getResource('Article');
        $article = $resource->create($testArticle);
        $this->assertInstanceOf(\Shopware\Models\Article\Article::class, $article);

        return $article;
    }

    /**
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function testBothActiveProductExported()
    {

        $this->createTestProduct(1, true);
        $this->createTestProduct(2, true);

        $this->assertEquals(2, $this->runExportAndReturnCount());

    }

    /**
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function testBothInActiveProductExported()
    {

        $this->createTestProduct(3, false);
        $this->createTestProduct(4, false);

        $this->assertEquals(0, $this->runExportAndReturnCount());

    }

    /**
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function testOneActiveOneInActiveProductExported()
    {

        $this->createTestProduct(5, true);
        $this->createTestProduct(6, false);

        $this->assertEquals(1, $this->runExportAndReturnCount());

    }

    /**
     * @return int
     */
    private function runExportAndReturnCount()
    {
        try {

            $shopKey = 'ABCD0815';

            $blController = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $blController->setShopKey($shopKey);

            $xmlDocument = $blController->getFindologicXml();

            $xml = new \SimpleXMLElement($xmlDocument);

            $this->sResetArticles();

            $count = (int)$xml->items->attributes()->count;

            return $count;

        } catch (\Exception $e) {
            echo "\nException: " . $e->getMessage();
        }

    }

    /**
     * Truncate all article related tables
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

        } catch (\Exception $e) {
            // if table does not exist - resume, it might be just an old SW version
        }
    }
}
