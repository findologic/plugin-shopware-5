<?php

namespace FinSearchUnified\Helper;

use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Customer\Group;
use Shopware\Models\Plugin\Plugin;
use Zend_Http_Client;
use Zend_Http_Client_Exception;
use Zend_Http_Response;

class UrlBuilder
{
    const BASE_URL = 'https://service.findologic.com/ps/xml_2.0/';
    const CDN_URL = 'https://cdn.findologic.com/static/';
    const JSON_CONFIG = '/config.json';
    const ALIVE_ENDPOINT = 'alivetest.php';
    const SEARCH_ENDPOINT = 'index.php';
    const NAVIGATION_ENPOINT = 'selector.php';
    const JSON_PATH = 'directIntegration';

    /**
     * @var Zend_Http_Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $shopkey;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var Group
     */
    private $customerGroup;

    /**
     * @var string
     */
    private $hashedKey;

    /**
     * @var string
     */
    private $configUrl;

    /**
     * UrlBuilder constructor.
     *
     * @param null|Zend_Http_Client $httpClient The Zend HTTP client to use.
     *
     * @throws \Exception
     */
    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient instanceof Zend_Http_Client ? $httpClient : new Zend_Http_Client();
        $this->shopUrl = explode('//', Shopware()->Modules()->Core()->sRewriteLink())[1];

        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('shopware.plugin_manager')
            ->getPluginByName(
                'FinSearchUnified'
            );

        $this->parameters = [
            'userip' => $this->getClientIp(),
            'revision' => $plugin->getVersion(),
        ];
    }

    private function getClientIp()
    {
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            // Check for multiple IPs passing through proxy
            $position = strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',');

            // If multiple IPs are passed, extract the first one
            if ($position !== false) {
                $ipAddress = substr($_SERVER['HTTP_X_FORWARDED_FOR'], 0, $position);
            } else {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } elseif ($_SERVER['HTTP_X_FORWARDED']) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ($_SERVER['HTTP_FORWARDED_FOR']) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_FORWARDED']) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = 'UNKNOWN';
        }

        $ipAddress = implode(',', array_unique(array_map('trim', explode(',', $ipAddress))));

        return $ipAddress;
    }

    /**
     * Never call this method in any constructor since Shopware can't guarantee that the relevant shop is already
     * loaded at that point. Therefore the master shops shopkey would be returned.
     * Caches and returns the current shop's shopkey.
     *
     * @return string
     */
    private function getShopkey()
    {
        if ($this->shopkey === null) {
            $this->shopkey = strtoupper(Shopware()->Config()->get('ShopKey'));
        }

        return $this->shopkey;
    }

    /**
     * Caches and returns the URL for the current shop's config JSON.
     *
     * @return string
     */
    private function getConfigUrl()
    {
        if ($this->configUrl === null) {
            $this->configUrl = self::CDN_URL . strtoupper(md5($this->getShopkey())) . self::JSON_CONFIG;
        }

        return $this->configUrl;
    }

    /**
     * @return bool
     */
    public function getConfigStatus()
    {
        try {
            $request = $this->httpClient->setUri($this->getConfigUrl());
            $requestHandler = $request->request();
            if ($requestHandler->getStatus() == 200) {
                $response = $requestHandler->getBody();
                $jsonResponse = json_decode($response, true);

                return (bool)$jsonResponse[self::JSON_PATH]['enabled'];
            }

            return false;
        } catch (Zend_Http_Client_Exception $e) {
            return false;
        }
    }

    /**
     * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
     *
     * @return null|Zend_Http_Response
     */
    public function buildQueryUrlAndGetResponse(Criteria $criteria)
    {
        /** @var \Shopware\Bundle\SearchBundle\Condition\SearchTermCondition $searchQuery */
        $searchQuery = $criteria->getBaseCondition('search');

        /** @var SearchBundle\Condition\CategoryCondition $catQuery */
        $catQuery = $criteria->getBaseCondition('category');

        $sortingQuery = $criteria->getSortings();

        /** @var SearchBundle\ConditionInterface[] $conditions */
        $conditions = $criteria->getConditions();
        if ($searchQuery instanceof SearchBundle\Condition\SearchTermCondition) {
            $this->buildKeywordQuery($searchQuery->getTerm());
        }
        if ($catQuery instanceof SearchBundle\Condition\CategoryCondition) {
            if ($catQuery->getCategoryIds() !== null && count($catQuery->getCategoryIds()) > 0) {
                $this->buildCategoryAttribute($catQuery->getCategoryIds());
            }
        }

        /** @var SearchBundle\SortingInterface $sorting */
        foreach ($sortingQuery as $sorting) {
            $this->buildSortingParameter($sorting);
        }

        $this->processQueryParameter($conditions, $criteria->getOffset(), $criteria->getLimit());

        return $this->callFindologicForXmlResponse();
    }

    /**
     * @param ConditionInterface[] $conditions
     * @param int $offset
     * @param int $itemsPerPage
     */
    private function processQueryParameter($conditions, $offset, $itemsPerPage)
    {
        /** @var ConditionInterface $condition */
        foreach ($conditions as $condition) {
            if ($condition instanceof SearchBundle\Condition\PriceCondition) {
                if ($condition->getMaxPrice() == 0 || $condition->getMaxPrice() === null) {
                    $max = PHP_INT_MAX;
                } else {
                    $max = $condition->getMaxPrice();
                }

                $this->buildPriceAttribute('min', $condition->getMinPrice());
                $this->buildPriceAttribute('max', $max);
            } elseif ($condition instanceof SearchBundle\Condition\ProductAttributeCondition) {
                $this->buildAttribute($condition->getField(), $condition->getValue());
            } elseif ($condition instanceof SearchBundle\Condition\SearchTermCondition) {
                /* @var SearchBundle\Condition\SearchTermCondition $condition */
                $this->buildKeywordQuery($condition->getTerm());
            } elseif ($condition instanceof SearchBundle\Condition\CategoryCondition) {
                /* @var SearchBundle\Condition\CategoryCondition $condition */
                $this->buildCategoryAttribute($condition->getCategoryIds());
            } else {
                continue;
            }
        }

        $this->parameters['first'] = $offset;

        // Don't request any products in this case, since Shopware most probably only needs
        // the total number of found products.
        if ($offset === 0 && $itemsPerPage === 1) {
            $this->parameters['count'] = 0;
        } else {
            $this->parameters['count'] = $itemsPerPage;
        }
    }

    /**
     * @param int $categoryId
     *
     * @return null|Zend_Http_Response
     */
    public function buildCategoryUrlAndGetResponse($categoryId)
    {
        $this->processQueryParameter(
            [new SearchBundle\Condition\CategoryCondition([$categoryId])],
            0,
            0
        );

        return $this->callFindologicForXmlResponse();
    }

    public function buildCompleteFilterList()
    {
        $this->processQueryParameter([], 0, 0);

        return $this->callFindologicForXmlResponse();
    }

    /**
     * @param SortingInterface $sorting
     */
    private function buildSortingParameter(SortingInterface $sorting)
    {
        if ($sorting instanceof SearchBundle\Sorting\PopularitySorting) {
            $this->parameters['order'] = urldecode('salesfrequency ' . $sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\PriceSorting) {
            $this->parameters['order'] = urldecode('price ' . $sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\ProductNameSorting) {
            $this->parameters['order'] = urldecode('label ' . $sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\ReleaseDateSorting) {
            $this->parameters['order'] = urldecode('dateadded ' . $sorting->getDirection());
        }
    }

    /**
     * @param int[] $categoryId
     */
    private function buildCategoryAttribute($categoryId)
    {
        $categories = [];

        if (Shopware()->Session()->offsetGet('isSearchPage')) {
            $categoryParameterName = 'attrib';
        } else {
            $categoryParameterName = 'selected';
        }

        foreach ($categoryId as $id) {
            $catString = StaticHelper::buildCategoryName($id, false);

            if ($catString !== null && $catString !== '') {
                $categories[] = $catString;
            }
        }

        if ($categories) {
            $this->parameters[$categoryParameterName]['cat'] = $categories;
        }
    }

    /**
     * @param string $key
     * @param float $value
     */
    private function buildPriceAttribute($key, $value)
    {
        $this->parameters['attrib']['price'][$key] = urldecode($value);
    }

    /**
     * @param string $key
     * @param array $value
     */
    private function buildAttribute($key, $value)
    {
        foreach ($value as $realValue) {
            $this->parameters['attrib'][$key][] = urldecode($realValue);
        }
    }

    /**
     * @param string $searchQuery
     */
    private function buildKeywordQuery($searchQuery)
    {
        $this->parameters['query'] = urldecode($searchQuery);
    }

    /**
     * @return null|Zend_Http_Response
     */
    private function callFindologicForXmlResponse()
    {
        if (Shopware()->Session()->offsetGet('isSearchPage')) {
            $endpoint = self::SEARCH_ENDPOINT;
        } else {
            $endpoint = self::NAVIGATION_ENPOINT;
        }

        $this->parameters['shopkey'] = $this->getShopkey();
        $url = sprintf(
            '%s%s%s?%s',
            self::BASE_URL,
            $this->shopUrl,
            $endpoint,
            http_build_query($this->parameters)
        );

        try {
            if ($this->isAlive()) {
                $request = $this->httpClient->setUri($url);
                $response = $request->request();
            } else {
                $response = null;
            }
        } catch (Zend_Http_Client_Exception $e) {
            $response = null;
        }

        return $response;
    }

    /**
     * @return Group
     */
    public function getCustomerGroup()
    {
        return $this->customerGroup;
    }

    /**
     * @param Group $customerGroup
     */
    public function setCustomerGroup(Group $customerGroup)
    {
        $this->customerGroup = $customerGroup;
        $this->hashedKey = StaticHelper::calculateUsergroupHash($this->getShopkey(), $customerGroup->getKey());
        $this->parameters['group'] = [$this->hashedKey];
    }

    /**
     * Returns true if the service is reachable and alive. Will return false in any other case.
     *
     * @return bool
     */
    private function isAlive()
    {
        $url = sprintf(
            '%s%s%s?shopkey=%s',
            self::BASE_URL,
            $this->shopUrl,
            self::ALIVE_ENDPOINT,
            $this->getShopkey()
        );

        try {
            $request = $this->httpClient->setUri($url);
            $response = $request->request();
            $isAlive = $response->isSuccessful() && strpos($response->getBody(), 'alive') !== false;
        } catch (Zend_Http_Client_Exception $e) {
            $isAlive = false;
        }

        return $isAlive;
    }
}
