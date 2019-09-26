<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use SimpleXMLElement;

interface PartialFacetHandlerInterface
{
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter);

    public function supportsFilter(SimpleXMLElement $filter);
}
