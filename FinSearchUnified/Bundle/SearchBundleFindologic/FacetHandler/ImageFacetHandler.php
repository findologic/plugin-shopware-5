<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Pool;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListItem;
use Shopware\Bundle\StoreFrontBundle\Struct\Media;
use Shopware\Components\HttpClient\GuzzleFactory;
use SimpleXMLElement;

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
    public function __construct(GuzzleFactory $guzzleFactory, array $guzzleConfig)
    {
        $this->guzzleClient = $guzzleFactory->createClient($guzzleConfig);
    }

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param SimpleXMLElement $filter
     *
     * @return MediaListFacetResult
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter)
    {
        $actives = [];

        $condition = $criteria->getCondition($facet->getName());

        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        $values = $this->getMediaListItems($actives, $filter->items->item);

        return new MediaListFacetResult(
            $facet->getName(),
            $criteria->hasCondition($facet->getName()),
            $facet->getLabel(),
            $values,
            $facet->getFormFieldName()
        );
    }

    /**
     * @param SimpleXMLElement $filter
     *
     * @return bool
     */
    public function supportsFilter(SimpleXMLElement $filter)
    {
        $type = (string)$filter->type;

        return ($type === 'image' || ($type === 'color' && $filter->items->item[0]->image));
    }

    /**
     * @param array $active
     * @param SimpleXMLElement $filterItems
     *
     * @return array
     */
    private function getMediaListItems(array $active, SimpleXMLElement $filterItems)
    {
        $listItems = [];
        $items = [];
        $requests = [];

        foreach ($filterItems as $filterItem) {

            $name = (string)$filterItem->name;
            $isActive = array_search($name, $active) !== false;

            if (empty($filterItem->image)) {

                $listItems[] = new MediaListItem(
                    $name,
                    $name,
                    $isActive
                );
            } else {

                $url = (string)$filterItem->image;

                $items[$url] = [
                    'name' => $name,
                    'active' => $isActive
                ];

                $requests[] = $this->guzzleClient->createRequest('HEAD', $url);

                $options = [
                    'complete' => function (CompleteEvent $event) use ($items) {

                        $url = $event->getRequest()->getUrl();
                        $data = $items[$url];

                        $media = new Media();
                        $media->setFile($url);

                        $listItems[] = new MediaListItem(
                            $data['name'],
                            $data['name'],
                            $data['active'],
                            $media
                        );
                    },
                    'error' => function (CompleteEvent $event) use ($items) {

                        $url = $event->getRequest()->getUrl();
                        $data = $items[$url];

                        $listItems[] = new MediaListItem(
                            $data['name'],
                            $data['name'],
                            $data['active']
                        );
                    }
                ];

                Pool::send($this->guzzleClient, $requests, $options);
            }
        }

        return $listItems;
    }
}
