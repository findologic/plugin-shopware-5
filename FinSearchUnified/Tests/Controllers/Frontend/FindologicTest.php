<?php

namespace FinSearchUnified\Tests\Controllers\Frontend;

use Enlight_Components_Test_Plugin_TestCase;
use Exception;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Components\Api\Manager;

class FindologicTest extends Enlight_Components_Test_Plugin_TestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    public function testExportHeadersAreSetCorrectly()
    {
        $this->createTestProduct(1, true);

        $shopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';
        $this->setConfig('ShopKey', $shopkey);

        $response = $this->dispatch(sprintf('findologic?shopkey=%s', $shopkey));
        $this->assertSame(200, $response->getHttpResponseCode());

        $responseHeaders = $response->getHeaders();
        $headerHandler = Shopware()->Container()->get('fin_search_unified.helper.header_handler');

        foreach ($responseHeaders as $responseHeader) {
            $expectedHeader = $headerHandler->getHeader($responseHeader['name']);
            if ($expectedHeader) {
                $this->assertSame($expectedHeader, $responseHeader['value']);
            }
        }
    }

    private function createTestProduct($number, $isActive, $categories = [])
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
                ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                ['link' => 'https://via.placeholder.com/100/09f/000.png'],
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

        if (!empty($categories)) {
            $assignedCategories = [];
            foreach ($categories as $category) {
                $assignedCategories[] = ['id' => $category];
            }
            $testArticle['categories'] = $assignedCategories;
        }

        try {
            $resource = Manager::getResource('Article');

            return $resource->create($testArticle);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return null;
    }
}
