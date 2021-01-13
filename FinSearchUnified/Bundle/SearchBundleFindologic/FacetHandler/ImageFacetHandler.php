<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ImageFilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\VendorImageFilter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Pool;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListItem;
use Shopware\Bundle\StoreFrontBundle\Struct\Media;
use Shopware\Components\HttpClient\GuzzleFactory;

use function array_search;

class ImageFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @var ClientInterface
     */
    private $guzzleClient;

    /**
     * @param GuzzleFactory $guzzleFactory
     * @param array $guzzleConfig
     */
    public function __construct(GuzzleFactory $guzzleFactory, array $guzzleConfig = [])
    {
        $this->guzzleClient = $guzzleFactory->createClient($guzzleConfig);
    }

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return MediaListFacetResult
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        $actives = [];

        $condition = $criteria->getCondition($facet->getName());

        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        $values = $this->getMediaListItems($actives, $filter->getValues());

        return new MediaListFacetResult(
            $facet->getName(),
            $criteria->hasCondition($facet->getName()),
            $facet->getLabel(),
            $values,
            $facet->getFormFieldName()
        );
    }

    /**
     * @param BaseFilter $filter
     *
     * @return bool
     */
    public function supportsFilter(BaseFilter $filter)
    {
        return $filter instanceof VendorImageFilter;
    }

    /**
     * @param array $actives
     * @param ImageFilterValue[] $filterItems
     *
     * @return array
     */
    private function getMediaListItems(array $actives, array $filterItems)
    {
        $items = [];
        foreach ($filterItems as $filterItem) {
            $index = $this->getItemIndex($filterItem->getName(), $actives);

            $media = new Media();
            $media->setFile($filterItem->getMedia()->getUrl());

            $items[] = new MediaListItem(
                $filterItem->getId(),
                $filterItem->getName(),
                $index !== false,
                $media
            );
        }

        return $items;
    }

    /**
     * @param string $name
     * @param array $orderedArray
     *
     * @return false|int|string
     */
    private function getItemIndex($name, array $orderedArray)
    {
        return array_search($name, $orderedArray, false);
    }
}
