<?php


namespace FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler;

use FinSearchUnified\Bundle\StoreFrontBundle\Service\CustomFacetServiceInterface;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Enlight_Controller_Request_RequestHttp as Request;

class FindologicFacetCriteriaRequestHandler
{
    /**
     * @var CustomFacetServiceInterface
     */
    private $facetService;

    public function __construct(
        CustomFacetServiceInterface $facetService
    ) {
        $this->facetService = $facetService;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(
        Request $request,
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        if (StaticHelper::useShopSearch()) {
            return;
        } elseif ($this->isSearchPage($request)) {
            $customFacets = $this->facetService->getList([], $context);
        } elseif ($this->isCategoryListing($request)) {
            $categoryId = (int) $request->getParam('sCategory');
            $customFacets = $this->facetService->getFacetsOfCategories([$categoryId], $context);
            $customFacets = array_shift($customFacets);
        } else {
            return;
        }

        /** @var CustomFacet[] $customFacets */
        foreach ($customFacets as $customFacet) {
            if (!$customFacet->getFacet()) {
                continue;
            }
            $facet = $customFacet->getFacet();
            $criteria->addFacet($facet);

            if ($facet instanceof ProductAttributeFacet) {
                $this->handleProductAttributeFacet($request, $criteria, $facet);
            }
        }
    }

    /**
     * @return bool
     */
    private function isCategoryListing(Request $request)
    {
        return strtolower($request->getControllerName()) === 'listing';
    }

    /**
     * @return bool
     */
    private function isSearchPage(Request $request)
    {
        $params = $request->getParams();

        return array_key_exists('sSearch', $params);
    }

    private function handleProductAttributeFacet(
        Request $request,
        Criteria $criteria,
        ProductAttributeFacet $facet
    ) {
        if (!$this->isAttributeInRequest($facet, $request)) {
            return;
        }
        $data = $request->getParam($facet->getFormFieldName());

        switch ($facet->getMode()) {
            case ProductAttributeFacet::MODE_BOOLEAN_RESULT:
                $criteria->addCondition(
                    new ProductAttributeCondition(
                        $facet->getField(),
                        ProductAttributeCondition::OPERATOR_NOT_IN,
                        [false]
                    )
                );

                return;

            case ProductAttributeFacet::MODE_RADIO_LIST_RESULT:
                $criteria->addCondition(
                    new ProductAttributeCondition(
                        $facet->getField(),
                        ProductAttributeCondition::OPERATOR_EQ,
                        $data
                    )
                );

                return;

            case ProductAttributeFacet::MODE_RANGE_RESULT:
                $range = [];
                if ($request->has('min' . $facet->getFormFieldName())) {
                    $range['min'] = $request->getParam('min' . $facet->getFormFieldName());
                }
                if ($request->has('max' . $facet->getFormFieldName())) {
                    $range['max'] = $request->getParam('max' . $facet->getFormFieldName());
                }
                $condition = new ProductAttributeCondition(
                    $facet->getField(),
                    ProductAttributeCondition::OPERATOR_BETWEEN,
                    $range
                );
                $criteria->addCondition($condition);

                return;

            case ProductAttributeFacet::MODE_VALUE_LIST_RESULT:
                $criteria->addCondition(
                    new ProductAttributeCondition(
                        $facet->getField(),
                        ProductAttributeCondition::OPERATOR_IN,
                        explode('|', $data)
                    )
                );

                return;
            default:
                return;
        }
    }

    /**
     * @return bool
     */
    private function isAttributeInRequest(ProductAttributeFacet $facet, Request $request)
    {
        $params = $request->getParams();

        if (array_key_exists($facet->getFormFieldName(), $params)) {
            return true;
        }
        if ($facet->getMode() !== ProductAttributeFacet::MODE_RANGE_RESULT) {
            return false;
        }

        return
            array_key_exists('min' . $facet->getFormFieldName(), $params)
            ||
            array_key_exists('max' . $facet->getFormFieldName(), $params)
            ;
    }
}
