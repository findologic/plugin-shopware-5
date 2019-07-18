<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Components\HttpClient\GuzzleFactory;
use SimpleXMLElement;

class ImageFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $guzzleClient;

    /**
     * @param GuzzleFactory $guzzleFactory
     * @param array $guzzleConfig
     */
    public function __construct(GuzzleFactory $guzzleFactory, array $guzzleConfig)
    {
        $this->guzzleClient = $guzzleFactory->createClient($guzzleConfig);
    }

    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter)
    {
        // TODO: Implement generatePartialFacet() method.
    }

    public function supportsFilter(SimpleXMLElement $filter)
    {
        $type = (string)$filter->type;

        return ($type === 'image' || ($type === 'color' && $filter->items->item[0]->image));
    }

    private function getMediaListItems()
    {

    }
}
