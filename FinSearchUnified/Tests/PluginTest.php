<?php

namespace FinSearchUnified\Tests;

use Exception;
use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\ShopwareProcess;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource\Article;
use SimpleXMLElement;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ'
        ],
    ];

    /** @var Manager */
    private $manager;

    protected function setUp()
    {
        parent::setUp();
        $this->manager = new Manager();
    }

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testCalculateGroupkey()
    {
        $shopkey = '0000000000000000ZZZZZZZZZZZZZZZZ';
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
            '2 active articles' => [
                [true, true],
                2,
                'Two articles were expected but %d were returned'
            ],
            '1 active and 1 inactive article' => [
                [true, false],
                1,
                'Only one article was expected but %d were returned'
            ],
            '2 inactive articles' => [
                [false, false],
                0,
                'No articles were expected but %d were returned'
            ],
        ];
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider articleProvider
     *
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
     *
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
            /** @var Article $resource */
            $resource = $this->manager->getResource('Article');
            $article = $resource->create($testArticle);

            return $article;
        } catch (Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }
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
            $shopKey = '0000000000000000ZZZZZZZZZZZZZZZZ';
            /** @var ShopwareProcess $blController */
            $blController = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $blController->setShopKey($shopKey);
            $xmlDocument = $blController->getFindologicXml();

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($xmlDocument);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return 0;
    }

    /**
     * Reset articles data after each test
     */
    protected function tearDown()
    {
        parent::tearDown();

        Utility::sResetArticles();
    }
}
