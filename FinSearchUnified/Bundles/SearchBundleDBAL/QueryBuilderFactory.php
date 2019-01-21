<?php

namespace FinSearchUnified\Bundles\SearchBundleDBAL;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\CriteriaAwareInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var \Enlight_Event_EventManager
     */
    protected $eventManager;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ConditionHandlerInterface[]
     */
    private $conditionHandlers;

    /**
     * @param Connection $connection
     * @param \Enlight_Event_EventManager $eventManager
     * @param ConditionHandlerInterface[] $conditionHandlers
     * @param ContainerInterface $container
     *
     * @throws \Enlight_Event_Exception
     */
    public function __construct(
        Connection $connection,
        \Enlight_Event_EventManager $eventManager,
        $conditionHandlers,
        ContainerInterface $container
    ) {
        $this->connection = $connection;
        $this->eventManager = $eventManager;
        $this->conditionHandlers = $conditionHandlers;

        $handlers = new ArrayCollection();
        $handlers = $this->eventManager->collect(
            'Shopware_SearchBundleDBAL_Collect_Condition_Handlers',
            $handlers
        );

        $this->conditionHandlers = array_merge($handlers->toArray(), $this->conditionHandlers);

        $container->set('shopware_searchdbal.condition_handlers', $this->conditionHandlers);
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     * @throws \Exception
     */
    public function createQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryBuilder();

        $this->prepareConditionHandlers($criteria);

        $query->from('s_articles', 'product');

        $query->leftJoin(
            'product',
            's_articles_details',
            'mainDetail',
            'mainDetail.id = product.main_detail_id'
        );

        $query->leftJoin(
            'product',
            's_articles_details',
            'variant',
            'variant.articleID = product.id AND variant.id != product.main_detail_id'
        );

        $this->addConditions($criteria, $query, $context);

        return $query;
    }

    /**
     * @param QueryBuilder $query
     */
    private function addSorting(QueryBuilder $query)
    {
        $query->addOrderBy('product.id', 'ASC');
    }

    /**
     * {@inheritdoc}
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->connection);
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     */
    public function createProductQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryWithSorting($criteria, $context);

        $select = $query->getQueryPart('select');

        $query->select([
            'SQL_CALC_FOUND_ROWS product.id AS __product_id',
            'mainDetail.ordernumber AS __main_detail_number',
            "GROUP_CONCAT(variant.ordernumber SEPARATOR ', ') AS __variant_numbers"
        ]);

        foreach ($select as $selection) {
            $query->addSelect($selection);
        }

        $query->addGroupBy('product.id, mainDetail.ordernumber');

        if ($criteria->getOffset()) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit()) {
            $query->setMaxResults($criteria->getLimit());
        }

        return $query;
    }

    /**
     * @param Criteria $criteria
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     *
     * @throws \Exception
     */
    private function addConditions(Criteria $criteria, QueryBuilder $query, ShopContextInterface $context)
    {
        foreach ($criteria->getConditions() as $condition) {
            $handler = $this->getConditionHandler($condition);
            $handler->generateCondition($condition, $query, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createQueryWithSorting(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQuery($criteria, $context);

        $this->addSorting($query);

        return $query;
    }

    /**
     * @param Criteria $criteria
     */
    private function prepareConditionHandlers($criteria)
    {
        foreach ($this->conditionHandlers as $handler) {
            if ($handler instanceof CriteriaAwareInterface) {
                $handler->setCriteria($criteria);
            }
        }
    }

    /**
     * @param ConditionInterface $condition
     *
     * @throws \Exception
     * @return ConditionHandlerInterface
     */
    private function getConditionHandler(ConditionInterface $condition)
    {
        foreach ($this->conditionHandlers as $handler) {
            if ($handler->supportsCondition($condition)) {
                return $handler;
            }
        }

        throw new \Exception(sprintf('Condition %s not supported', get_class($condition)));
    }
}
