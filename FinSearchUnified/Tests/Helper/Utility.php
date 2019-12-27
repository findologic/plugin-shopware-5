<?php

namespace FinSearchUnified\Tests\Helper;

use Exception;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource\Article;
use Shopware\Components\Api\Resource\Category;

class Utility
{
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

    /**
     * @param $number
     * @param $isActive
     * @param array $override
     *
     * @return \Shopware\Models\Article\Article
     */
    public static function createTestProduct($number, $isActive, array $override = [])
    {
        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 5, 'active' => true],
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

        $testArticle = array_merge($testArticle, $override);

        try {
            /** @var Article $resource */
            $resource = Manager::getResource('Article');

            return $resource->create($testArticle);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }
    }

    /**
     * @param $index
     * @param array $override
     *
     * @return \Shopware\Models\Category\Category
     */
    public static function createTestCategory($index, array $override = [])
    {
        $testData = [
            'name' => 'Test-category-' . $index,
            'parentId' => 3,
            'metaDescription' => 'metaTest',
            'metaKeywords' => 'keywordTest',
            'cmsHeadline' => 'headlineTest',
            'cmsText' => 'cmsTextTest',
            'active' => true,
            'noViewSelect' => true,
            'attribute' => [
                '1' => 'Attribute1',
                '2' => 'Attribute2'
            ]
        ];

        $testData = array_merge($testData, $override);

        try {
            /** @var Category $resource */
            $resource = Manager::getResource('Category');

            return $resource->create($testData);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }
    }
}
