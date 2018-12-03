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
        $criteria = new Criteria();
        $criteria->limit(10);

        if (Shopware()->Config()->get('hideNoInStock')) {
            $criteria->addCondition(new IsAvailableCondition());
        }
        $criteria->addCondition(new HasActiveChildCategoryOfCurrentShopCondition(
            Shopware()->Shop()->getCategory()->getId()
        ));
        $criteria->addCondition(new HasActiveCategoryCondition());

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->addCriteria($criteria);

        $query = $builder->getQuery();
        // TODO implement the case to check if query is generated correctly
    }
}
