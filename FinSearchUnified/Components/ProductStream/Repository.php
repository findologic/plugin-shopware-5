<?php

namespace FinSearchUnified\Components\ProductStream;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\ProductStream\RepositoryInterface;
use FinSearchUnified\Helper\StaticHelper;

class Repository implements RepositoryInterface
{
    /**
     * @var RepositoryInterface
     */
    private $originalRepository;

    /**
     * @param RepositoryInterface $repository
     */
    public function __construct(RepositoryInterface $repository)
    {
        $this->originalRepository = $repository;
    }

    /**
     * @param Criteria $criteria
     * @param int      $productStreamId
     */
    public function prepareCriteria(Criteria $criteria, $productStreamId)
    {
        $module = Shopware()->Front()->Request()->getModuleName();

        if ($module === 'backend' || StaticHelper::useShopSearch()) {
            $this->originalRepository->prepareCriteria($criteria, $productStreamId);
        }

        return;
    }

    /**
     * @param array $serializedConditions
     *
     * @return object[]
     */
    public function unserialize($serializedConditions)
    {
        return $this->originalRepository->unserialize($serializedConditions);
    }
}
