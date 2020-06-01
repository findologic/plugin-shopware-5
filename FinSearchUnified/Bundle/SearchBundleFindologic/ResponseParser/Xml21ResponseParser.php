<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

use Enlight_Components_Db_Adapter_Pdo_Mysql;
use Exception;
use FINDOLOGIC\Api\Responses\Xml21\Properties\LandingPage;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Promotion as ApiPromotion;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\CategoryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\QueryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\SearchTermQueryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\VendorInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use Shopware\Bundle\StoreFrontBundle\Service\ProductNumberServiceInterface;

class Xml21ResponseParser extends ResponseParser
{
    /**
     * @return string[]
     */
    public function getProducts()
    {
        $foundProducts = [];

        try {
            $container = Shopware()->Container();
            /** @var ProductNumberServiceInterface $productService */
            $productService = $container->get('shopware_storefront.product_number_service');

            foreach ($this->response->getProducts() as $product) {
                try {
                    $articleId = $product->getId();

                    $ordernumber = $productService->getMainProductNumberById($articleId);

                    if ($articleId === '' || $articleId === null) {
                        continue;
                    }
                    $baseArticle = [];
                    $baseArticle['orderNumber'] = $ordernumber;
                    $baseArticle['detailId'] = $this->getDetailIdForOrdernumber($ordernumber);
                    $foundProducts[$articleId] = $baseArticle;
                } catch (Exception $ex) {
                    // No Mapping for Search Results
                    continue;
                }
            }
        } catch (Exception $ex) {
            // Logging Function
        }

        return $foundProducts;
    }

    /**
     * @param $ordernumber
     *
     * @return string|bool
     */
    public function getDetailIdForOrdernumber($ordernumber)
    {
        /** @var Enlight_Components_Db_Adapter_Pdo_Mysql $db */
        $db = Shopware()->Container()->get('db');
        $checkForArticle = $db->fetchRow('SELECT id AS id FROM s_articles_details WHERE ordernumber=?', [$ordernumber]);

        if (isset($checkForArticle['id'])) {
            return $checkForArticle['id'];
        }

        return false;
    }

    /**
     * @return string|null
     */
    public function getLandingPageUri()
    {
        $landingPage = $this->response->getLandingPage();
        if ($landingPage instanceof LandingPage) {
            return $landingPage->getLink();
        }

        return null;
    }

    /**
     * @return SmartDidYouMean
     */
    public function getSmartDidYouMean()
    {
        $query = $this->response->getQuery();
        $request = Shopware()->Front()->Request();

        $originalQuery = $query->getOriginalQuery() ? $query->getOriginalQuery()->getValue() : '';
        $alternativeQuery = $query->getAlternativeQuery();
        $didYouMeanQuery = $query->getDidYouMeanQuery();
        $type = $query->getQueryString()->getType();

        return new SmartDidYouMean(
            $originalQuery,
            $alternativeQuery,
            $didYouMeanQuery,
            $type,
            $request ? $request->getControllerName() : ''
        );
    }

    /**
     * @return Promotion|null
     */
    public function getPromotion()
    {
        $promotion = $this->response->getPromotion();

        if ($promotion instanceof ApiPromotion) {
            return new Promotion($promotion->getImage(), $promotion->getLink());
        }

        return null;
    }

    /**
     * @param SmartDidYouMean $smartDidYouMean
     *
     * @return QueryInfoMessage
     */
    public function getQueryInfoMessage(SmartDidYouMean $smartDidYouMean)
    {
        $queryString = $this->response->getQuery()->getQueryString()->getValue();
        $params = Shopware()->Front()->Request()->getParams();

        if ($this->hasAlternativeQuery($queryString)) {
            return $this->buildSearchTermQueryInfoMessage($smartDidYouMean->getAlternativeQuery());
        }

        if ($this->hasQuery($queryString)) {
            return $this->buildSearchTermQueryInfoMessage($queryString);
        }

        if ($this->isFilterSet($params, 'cat')) {
            return $this->buildCategoryQueryInfoMessage($params);
        }

        if ($this->isFilterSet($params, 'vendor')) {
            return $this->buildVendorQueryInfoMessage($params);
        }

        return QueryInfoMessage::buildInstance(QueryInfoMessage::TYPE_DEFAULT);
    }

    /**
     * @param string $query
     *
     * @return SearchTermQueryInfoMessage
     */
    private function buildSearchTermQueryInfoMessage($query)
    {
        /** @var SearchTermQueryInfoMessage $queryInfoMessage */
        $queryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_QUERY,
            $query
        );

        return $queryInfoMessage;
    }

    /**
     * @param array $params
     *
     * @return CategoryInfoMessage
     */
    private function buildCategoryQueryInfoMessage(array $params)
    {
        $filters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        $categories = explode('_', $params['cat']);
        $category = end($categories);

        /** @var CategoryInfoMessage $categoryInfoMessage */
        $categoryInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_CATEGORY,
            null,
            $filters['cat']->getDisplay(),
            $category
        );

        return $categoryInfoMessage;
    }

    /**
     * @param array $params
     *
     * @return VendorInfoMessage
     */
    private function buildVendorQueryInfoMessage(array $params)
    {
        $filters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        /** @var VendorInfoMessage $vendorInfoMessage */
        $vendorInfoMessage = QueryInfoMessage::buildInstance(
            QueryInfoMessage::TYPE_VENDOR,
            null,
            $filters['vendor']->getDisplay(),
            $params['vendor']
        );

        return $vendorInfoMessage;
    }

    /**
     * @param string|null $queryString
     *
     * @return bool
     */
    private function hasAlternativeQuery($queryString)
    {
        $queryStringType = $this->response->getQuery()->getQueryString()->getType();

        return !empty($queryString) && (($queryStringType === 'corrected') || ($queryStringType === 'improved'));
    }

    /**
     * @param string|null $queryString
     *
     * @return bool
     */
    private function hasQuery($queryString)
    {
        return !empty($queryString);
    }

    /**
     * @param array $params
     * @param string $name
     *
     * @return bool
     */
    private function isFilterSet(array $params, $name)
    {
        return isset($params[$name]) && !empty($params[$name]);
    }

    /**
     * @return Filter[]
     */
    public function getFilters()
    {
        $apiFilters = array_merge($this->response->getMainFilters(), $this->response->getOtherFilters());

        $filters = [];
        foreach ($apiFilters as $apiFilter) {
            $filter = Filter::getInstance($apiFilter);

            if ($filter && count($filter->getValues()) >= 1) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }
}
