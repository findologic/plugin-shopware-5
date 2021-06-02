<?php

namespace FinSearchUnified\Tests\Helper;

use Exception;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\BusinessLogic\ExportErrorInformation;
use FinSearchUnified\ShopwareProcess;
use Shopware\Components\Api\Manager;
use Shopware\Models\Article\Article;
use Shopware\Models\Shop\Shop;
use SimpleXMLElement;
use Zend_Cache_Exception;

class Utility
{
    /**
     * @param int|string $number
     * @param bool $isActive
     * @param array $categories
     *
     * @return Article|null
     */
    public static function createTestProduct($number, $isActive, $categories = [])
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

    /**
     * @param Shop $baseShop
     * @return Shop|null
     */
    public function createSubShop(Shop $baseShop)
    {
        $subShop = [
            'name' => 'SubShop',
            'categoryId' => $baseShop->getCategory()->getId(),
            'localeId' => $baseShop->getLocale()->getId(),
            'currencyId' => $baseShop->getCurrency()->getId(),
            'customerGroupId' => $baseShop->getCustomerGroup()->getId(),
        ];

        try {
            $resource = Manager::getResource('Shop');

            return $resource->create($subShop);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return null;
    }

    public function createTestProductsWithIdAndVendor()
    {
        $testArticles = [
            [
                'id' => 1,
                'name' => 'FindologicArticle1',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC1',
                    'active' => true,
                    'inStock' => 16,
                    'ean' => '2',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 2,
                'name' => 'FindologicArticle2',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor2',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC2',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 3,
                'name' => 'FindologicArticle3',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor2',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC3',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 4,
                'name' => 'FindologicArticle4',
                'active' => false,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC4',
                    'active' => false,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 5,
                'name' => 'FindologicArticle5',
                'active' => false,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 75]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC5',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 6,
                'name' => 'FindologicArticle6',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC6',
                    'active' => true,
                    'inStock' => 16,
                    'supplierNumber' => '2',
                    'ean' => '1',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 7,
                'name' => 'FindologicArticle7',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => '2',
                    'active' => true,
                    'inStock' => 16,
                    'supplierNumber' => 1,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 8,
                'name' => 'FindologicArticle8',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC8',
                    'active' => false,
                    'inStock' => 16,
                    'ean' => '1',
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ]
        ];

        foreach ($testArticles as $testArticle) {
            try {
                $resource = Manager::getResource('Article');

                $resource->create($testArticle);
            } catch (Exception $e) {
                echo sprintf('Exception: %s', $e->getMessage());
            }
        }
    }

    /**
     * Method to run the actual export functionality and parse the xml to return the
     * number of articles returned
     *
     * @return int
     */
    public static function runExportAndReturnCount()
    {
        try {
            /** @var ShopwareProcess $shopwareProcess */
            $shopwareProcess = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $shopwareProcess->setShopKey('ABCDABCDABCDABCDABCDABCDABCDABCD');
            $shopwareProcess->setUpExportService();
            $xmlDocument = $shopwareProcess->getFindologicXml(0, 20);

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($xmlDocument);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return 0;
    }

    /**
     * Method to run the actual export functionality with a productId and parse the xml or json to return the
     * number of articles or the error JSON string
     *
     * @param int $productId
     *
     * @return int|array<string[]|ExportErrorInformation[]>
     */
    public function runExportAndReturnCountOrErrors($productId = null)
    {
        try {
            /** @var ShopwareProcess $shopwareProcess */
            $shopwareProcess = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $shopwareProcess->setShopKey('ABCDABCDABCDABCDABCDABCDABCDABCD');
            $shopwareProcess->setUpExportService();
            $document = $shopwareProcess->getProductsById($productId);

            if ($shopwareProcess->getExportService()->getErrorCount() > 0) {
                return json_encode([
                    'errors' => [
                        'general' => $shopwareProcess->getExportService()->getGeneralErrors(),
                        'products' => $shopwareProcess->getExportService()->getProductErrors()
                    ]
                ]);
            }

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($document);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return 0;
    }

    /**
     * Delete all articles
     */
    public static function sResetArticles()
    {
        try {
            Shopware()->Db()->exec(
                'SET foreign_key_checks = 0;
                TRUNCATE `s_addon_premiums`;
                TRUNCATE `s_articles`;
                TRUNCATE `s_articles_also_bought_ro`;
                TRUNCATE `s_articles_attributes`;
                TRUNCATE `s_articles_avoid_customergroups`;
                TRUNCATE `s_articles_categories`;
                TRUNCATE `s_articles_categories_ro`;
                TRUNCATE `s_articles_categories_seo`;
                TRUNCATE `s_articles_details`;
                TRUNCATE `s_articles_downloads`;
                TRUNCATE `s_articles_downloads_attributes`;
                TRUNCATE `s_articles_esd`;
                TRUNCATE `s_articles_esd_attributes`;
                TRUNCATE `s_articles_esd_serials`;
                TRUNCATE `s_articles_img`;
                TRUNCATE `s_articles_img_attributes`;
                TRUNCATE `s_articles_information`;
                TRUNCATE `s_articles_information_attributes`;
                TRUNCATE `s_articles_notification`;
                TRUNCATE `s_articles_prices`;
                TRUNCATE `s_articles_prices_attributes`;
                TRUNCATE `s_articles_relationships`;
                TRUNCATE `s_articles_similar`;
                TRUNCATE `s_articles_similar_shown_ro`;
                TRUNCATE `s_articles_supplier`;
                TRUNCATE `s_articles_supplier_attributes`;
                TRUNCATE `s_articles_top_seller_ro`;
                TRUNCATE `s_articles_translations`;
                TRUNCATE `s_articles_vote`;
                TRUNCATE `s_article_configurator_dependencies`;
                TRUNCATE `s_article_configurator_groups`;
                TRUNCATE `s_article_configurator_options`;
                TRUNCATE `s_article_configurator_option_relations`;
                TRUNCATE `s_article_configurator_price_variations`;
                TRUNCATE `s_article_configurator_sets`;
                TRUNCATE `s_article_configurator_set_group_relations`;
                TRUNCATE `s_article_configurator_set_option_relations`;
                TRUNCATE `s_article_configurator_templates`;
                TRUNCATE `s_article_configurator_templates_attributes`;
                TRUNCATE `s_article_configurator_template_prices`;
                TRUNCATE `s_article_configurator_template_prices_attributes`;
                TRUNCATE `s_article_img_mappings`;
                TRUNCATE `s_article_img_mapping_rules`;
                TRUNCATE `s_filter_articles`;
                SET foreign_key_checks = 1;'
            );
        } catch (Exception $ignored) {
        }
    }

    public static function getDemoXML($file = 'demoResponse.xml')
    {
        $response = file_get_contents(__DIR__ . '/../MockData/XMLResponse/' . $file);

        return new SimpleXMLElement($response);
    }

    /**
     * Allows to set a Shopware config
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws Zend_Cache_Exception
     */
    public static function setConfig($name, $value)
    {
        Shopware()->Container()->get('config_writer')->save($name, $value);
        Shopware()->Container()->get('cache')->clean();
        Shopware()->Container()->get('config')->setShop(Shopware()->Shop());
    }

    public static function getDemoResponse($file = 'demoResponse.xml')
    {
        $response = file_get_contents(__DIR__ . '/../MockData/XMLResponse/' . $file);

        return new Xml21Response($response);
    }
}
