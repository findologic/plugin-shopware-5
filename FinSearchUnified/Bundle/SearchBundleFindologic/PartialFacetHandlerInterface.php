<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;

interface PartialFacetHandlerInterface
{
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter);

    public function supportsFilter(BaseFilter $filter);
}
