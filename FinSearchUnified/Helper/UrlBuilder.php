<?php

namespace FinSearchUnified\Helper;

use FinSearchUnified\ShopwareProcess;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Customer\Group;
use Shopware\Models\Plugin\Plugin;

class UrlBuilder
{
    const BASE_URL = 'https://service.findologic.com/ps/xml_2.0/';
    const CDN_URL = 'https://cdn.findologic.com/static/';
    const JSON_CONFIG = '/config.json';
    const ALIVE_ENDPOINT = 'alivetest.php';
    const JSON_PATH = 'directIntegration';

    private $httpClient;

    private $shopKey;

    private $shopUrl;

    private $parameters;

    private $customerGroup;

    private $hashedKey;

    private $configUrl;

    /**
     * UrlBuilder constructor.
     */
    public function __construct()
    {
        $this->httpClient = new \Zend_Http_Client();
        $this->shopKey = Shopware()->Config()->get('ShopKey');
        $this->shopUrl = explode('//', Shopware()->Modules()->Core()->sRewriteLink())[1];
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('shopware.plugin_manager')->getPluginByName('FinSearchUnified');
        $this->parameters = [
            'shopkey'  => $this->shopKey,
            'userip'   => self::getClientIpServer(),
            'revision' => $plugin->getVersion(),
        ];
        $this->configUrl = self::CDN_URL.strtoupper(md5($this->shopKey)).self::JSON_CONFIG;
    }

    public static function getClientIpServer()
    {
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_X_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ($_SERVER['HTTP_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    /**
     * @return bool
     */
    public function getConfigStatus()
    {
        try {
            $request = $this->httpClient->setUri($this->configUrl);
            $requestHandler = $request->request();
            if ($requestHandler->getStatus() == 200) {
                $response = $requestHandler->getBody();
                $jsonResponse = json_decode($response, true);

                return (bool) $jsonResponse[self::JSON_PATH]['enabled'];
            }

            return false;
        } catch (\Zend_Http_Client_Exception $e) {
            return false;
        }
    }

    /**
     * @param \Shopware\Bundle\SearchBundle\Criteria $criteria
     *
     * @return null|\Zend_Http_Response
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
     * @param int                  $offset
     * @param int                  $itemsPerPage
     */
    private function processQueryParameter($conditions, $offset, $itemsPerPage)
    {
        /** @var ConditionInterface $condition */
        foreach ($conditions as $condition) {
            if ($condition instanceof SearchBundle\Condition\PriceCondition) {
                $max = $condition->getMaxPrice() == 0 || $condition->getMaxPrice() === null ? PHP_INT_MAX : $condition->getMaxPrice();
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

        if (array_key_exists('catFilter', $_GET) && !empty($_GET['catFilter'])) {
            if (!is_array($this->parameters['attrib']['cat'])) {
                $this->parameters['attrib']['cat'] = [];
            }

            $this->parameters['attrib']['cat'][] = urldecode($_GET['catFilter']);
        }

        $this->parameters['first'] = $offset;
        $this->parameters['count'] = $itemsPerPage;
    }

    /**
     * @param int $categoryId
     *
     * @return null|\Zend_Http_Response
     */
    public function buildCategoryUrlAndGetResponse($categoryId)
    {
        $this->buildCategoryAttribute([$categoryId]);

        return $this->callFindologicForXmlResponse();
    }

    public function buildCompleteFilterList(){
        return $this->callFindologicForXmlResponse();
    }

    /**
     * @param SortingInterface $sorting
     */
    private function buildSortingParameter(SortingInterface $sorting)
    {
        if ($sorting instanceof SearchBundle\Sorting\PopularitySorting) {
            $this->parameters['order'] = urldecode('salesfrequency '.$sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\PriceSorting) {
            $this->parameters['order'] = urldecode('price '.$sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\ProductNameSorting) {
            $this->parameters['order'] = urldecode('label '.$sorting->getDirection());
        } elseif ($sorting instanceof SearchBundle\Sorting\ReleaseDateSorting) {
            $this->parameters['order'] = urldecode('dateadded '.$sorting->getDirection());
        }
    }

    /**
     * @param int[] $categoryId
     */
    private function buildCategoryAttribute($categoryId)
    {
        $attribCat = [];

        foreach ($categoryId as $id) {
            $catString = StaticHelper::buildCategoryName($id);

            if ($catString !== null && $catString !== '') {
                $attribCat[] = urldecode($catString);
            }
        }

        if ($attribCat) {
            $this->parameters['attrib']['cat'] = $attribCat;
        }
    }

    /**
     * @param string $key
     * @param float  $value
     */
    private function buildPriceAttribute($key, $value)
    {
        $this->parameters['attrib']['price'][$key] = urldecode($value);
    }

    /**
     * @param string $key
     * @param array  $value
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
     * @return null|\Zend_Http_Response
     */
    private function callFindologicForXmlResponse()
    {
        $url = self::BASE_URL.$this->shopUrl.'index.php?'.http_build_query($this->parameters);
        try {
            $request = $this->httpClient->setUri($url);

            return $request->request();
        } catch (\Zend_Http_Client_Exception $e) {
            return;
        }
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
        $this->hashedKey = ShopwareProcess::calculateUsergroupHash($this->shopKey, $customerGroup->getKey());
        $this->parameters['group'] = [$this->hashedKey];
    }
}
