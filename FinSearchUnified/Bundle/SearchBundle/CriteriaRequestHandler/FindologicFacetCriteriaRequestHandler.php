<?php

namespace FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler;

use Enlight_Controller_Request_RequestHttp as Request;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\StoreFrontBundle\Service\CustomFacetServiceInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\CombinedCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Bundle\SearchBundle\Facet\CombinedConditionFacet;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class FindologicFacetCriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    /**
     * @var CustomFacetServiceInterface
     */
    private $facetService;

    public function __construct(CustomFacetServiceInterface $facetService)
    {
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
        }

        if ($this->isSearchPage($request)) {
            $customFacets = $this->facetService->getList([], $context);
        } elseif ($this->isCategoryListing($request)) {
            $categoryId = (int)$request->getParam('sCategory');
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
     * @param Request $request
     *
     * @return bool
     */
    private function isCategoryListing(Request $request)
    {
        return strtolower($request->getControllerName()) === 'listing';
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    private function isSearchPage(Request $request)
    {
        $params = $request->getParams();

        return array_key_exists('sSearch', $params) || strtolower($request->getControllerName()) === 'search';
    }

    /**
     * @param Request $request
     * @param Criteria $criteria
     * @param ProductAttributeFacet $facet
     */
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
            case ProductAttributeFacet::MODE_RADIO_LIST_RESULT:
                $criteria->addCondition(
                    new ProductAttributeCondition(
                        $facet->getField(),
                        Operator::EQ,
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
                    Operator::BETWEEN,
                    $range
                );
                $criteria->addCondition($condition);

                return;
            case ProductAttributeFacet::MODE_VALUE_LIST_RESULT:
                $criteria->addCondition(
                    new ProductAttributeCondition(
                        $facet->getField(),
                        Operator::IN,
                        explode('|', $data)
                    )
                );

                return;
            default:
                return;
        }
    }

    /**
     * @param Request $request
     * @param Criteria $criteria
     * @param CombinedConditionFacet $facet
     */
    private function handleCombinedConditionFacet(
        Request $request,
        Criteria $criteria,
        CombinedConditionFacet $facet
    ) {
        if (!$request->has($facet->getRequestParameter())) {
            return;
        }
        $criteria->addCondition(
            new CombinedCondition(
                $facet->getConditions()
            )
        );
    }

    /**
     * @param ProductAttributeFacet $facet
     * @param Request $request
     *
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

        return array_key_exists('min' . $facet->getFormFieldName(), $params)
            || array_key_exists('max' . $facet->getFormFieldName(), $params);
    }
}
