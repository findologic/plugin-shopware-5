<?php

namespace FinSearchUnified\Helper;

use Enlight_View_Default;
use SimpleXMLElement;

class QueryInfoMessageParser
{
    /**
     * @var SimpleXMLElement
     */
    private $xmlResponse;

    /**
     * @var Enlight_View_Default
     */
    private $view;

    /**
     * @var string
     */
    private $vendor;

    /**
     * @var string
     */
    private $category;

    /**
     * @var string
     */
    private $smartQuery;

    /**
     * @var string
     */
    private $filterName;

    /**
     * @var string
     */
    private $snippetType;

    public function __construct(SimpleXMLElement $xmlResponse, Enlight_View_Default $view)
    {
        $this->xmlResponse = $xmlResponse;
        $this->view = $view;

        $this->parse();
    }

    private function parse()
    {
        $query = $this->xmlResponse->query;
        $queryStringType = (string)$query->queryString->attributes()->type;
        $queryString = (string)$query->queryString;

        $snippetManager = Shopware()->Container()->get('snippets')->getNamespace('frontend/search/query_info_message');

        $request = Shopware()->Front()->Request();
        $params = $request->getParams();

        if ((($queryStringType === 'corrected') && !empty($queryString)) ||
            (($queryStringType === 'improved') && !empty($queryString))) {
            $this->setSnippetType('query');
            $finSmartDidYouMean = $this->view->getAssign('finSmartDidYouMean');
            $this->setSmartQuery($finSmartDidYouMean['alternative_query']);
        } elseif (!empty($queryString)) {
            $this->setSnippetType('query');
            $this->setSmartQuery($queryString);
        } elseif (isset($params['cat']) && !empty($params['cat'])) {
            $categories = explode('_', $params['cat']);
            $this->setCategory(end($categories));
            $this->setFilterName($snippetManager->get('frontend/search/query_info_message/filter_category'));
            $this->setSnippetType('cat');
        } elseif (isset($params['vendor']) && !empty($params['vendor'])) {
            $this->setVendor($params['vendor']);
            $this->setFilterName($snippetManager->get('frontend/search/query_info_message/filter_vendor'));
            $this->setSnippetType('vendor');
        } else {
            $this->setSnippetType('default');
        }
    }

    /**
     * @return string
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * @param string $vendor
     */
    public function setVendor($vendor)
    {
        $this->vendor = $vendor;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param string $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * @return string
     */
    public function getSmartQuery()
    {
        return $this->smartQuery;
    }

    /**
     * @param string $smartQuery
     */
    public function setSmartQuery($smartQuery)
    {
        $this->smartQuery = $smartQuery;
    }

    /**
     * @return string
     */
    public function getFilterName()
    {
        return $this->filterName;
    }

    /**
     * @param string $filterName
     */
    public function setFilterName($filterName)
    {
        $this->filterName = $filterName;
    }

    /**
     * @return string
     */
    public function getSnippetType()
    {
        return $this->snippetType;
    }

    /**
     * @param string $snippetType
     */
    public function setSnippetType($snippetType)
    {
        $this->snippetType = $snippetType;
    }
}
