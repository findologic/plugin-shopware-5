<?php

namespace FinSearchUnified\tests\Bundles\SearchBundles\Condition;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveChildCategoryOfCurrentShopCondition;
use Shopware\Bundle\SearchBundle\Condition\IsAvailableCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;

class HasActiveCategoryConditionTest extends TestCase
{
    public function testGenerateCondition()
    {
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap([
                ['hideNoInStock', true],
                ['ShopKey', 'ABCD0815']
            ]);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        $criteria = new Criteria();

        if (Shopware()->Config()->get('hideNoInStock')) {
            $criteria->addCondition(new IsAvailableCondition());
        }
        $criteria->addCondition(new HasActiveChildCategoryOfCurrentShopCondition(
            Shopware()->Shop()->getCategory()->getId()
        ));
        $criteria->addCondition(new HasActiveCategoryCondition());
    }
}
