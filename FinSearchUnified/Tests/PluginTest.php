<?php

namespace FinSearchUnified\Tests;

use Exception;
use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\ShopwareProcess;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Components\Api\Manager;
use Shopware\Components\Logger;
use Shopware\Models\Article\Article;
use SimpleXMLElement;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => '8D6CA2E49FB7CD09889CC0E2929F86B0'
        ],
    ];

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('fin_search_unified.article_model_factory');
        Shopware()->Container()->load('fin_search_unified.article_model_factory');
        Shopware()->Container()->reset('pluginlogger');
        Shopware()->Container()->load('pluginlogger');

        Utility::sResetArticles();
    }

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testCalculateGroupkey()
    {
        $shopkey = '8D6CA2E49FB7CD09889CC0E2929F86B0';
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
                'articlesActiveStatus' => [true, true],
                'expectedCount' => 2,
                'errorMessage' => 'Two articles were expected but %d were returned'
            ],
            '1 active and 1 inactive article' => [
                'articlesActiveStatus' => [true, false],
                'expectedCount' => 1,
                'errorMessage' => 'Only one article was expected but %d were returned'
            ],
            '2 inactive articles' => [
                'articlesActiveStatus' => [false, false],
                'expectedCount' => 0,
                'errorMessage' => 'No articles were expected but %d were returned'
            ],
        ];
    }

    /**
     * @dataProvider articleProvider
     *
     * @param array $articlesActiveStatus
     * @param int $expectedCount
     * @param string $errorMessage
     */
    public function testArticleExport($articlesActiveStatus, $expectedCount, $errorMessage)
    {
        // Create articles with the provided data to test the export functionality
        foreach ($articlesActiveStatus as $i => $iValue) {
            $this->createTestProduct($i, $iValue);
        }
        $actual = $this->runExportAndReturnCount();
        $this->assertEquals($expectedCount, $actual, sprintf($errorMessage, $actual));
    }

    /**
     * Create test products for the export
     *
     * @param int $number
     * @param bool $isActive
     *
     * @return Article|null
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
            $resource = Manager::getResource('Article');

            return $resource->create($testArticle);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return null;
    }

    public function testEmptyValueNotAllowedExceptionIsThrownInExport()
    {
        $this->markTestSkipped('Skipping test due to exception class namespace issue');

        // Create articles with the provided data to test the export functionality
        $this->createTestProduct('_SOMENUMBER', true);
        $findologicArticleFactoryMock = $this->createMock(FindologicArticleFactory::class);
        $findologicArticleFactoryMock->expects($this->once())->method('create')->willThrowException(
            new EmptyValueNotAllowedException()
        );
        Shopware()->Container()->set('fin_search_unified.article_model_factory', $findologicArticleFactoryMock);

        $loggerMock = $this->createMock(Logger::class);

        $expectedMessage = sprintf(
            "Product with id '%s' could not be exported. It appears to has empty values assigned to it. " .
            'If you see this message in your logs, please report this as a bug',
            'FINDOLOGIC_SOMENUMBER'
        );

        $loggerMock->expects($this->once())->method('info')->with($expectedMessage);

        Shopware()->Container()->set('pluginlogger', $loggerMock);
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
            $shopKey = '8D6CA2E49FB7CD09889CC0E2929F86B0';
            /** @var ShopwareProcess $blController */
            $blController = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $blController->setShopKey($shopKey);
            $xmlDocument = $blController->getFindologicXml();

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($xmlDocument);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return 0;
    }
}
