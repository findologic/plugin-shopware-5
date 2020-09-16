<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\QueryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Components\ConfigLoader;
use InvalidArgumentException;

abstract class ResponseParser
{
    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ConfigLoader
     */
    protected $configLoader;

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->configLoader = Shopware()->Container()->get('fin_search_unified.config_loader');
    }

    /**
     * @return ResponseParser
     */
    public static function getInstance(Response $response)
    {
        if ($response instanceof Xml21Response) {
            return new Xml21ResponseParser($response);
        }

        throw new InvalidArgumentException('Unsupported response format.');
    }

    /**
     * @return string[]
     */
    abstract public function getProducts();

    /**
     * @return string|null
     */
    abstract public function getLandingPageUri();

    /**
     * @return SmartDidYouMean
     */
    abstract public function getSmartDidYouMean();

    /**
     * @return Promotion
     */
    abstract public function getPromotion();

    /**
     * @param SmartDidYouMean $smartDidYouMean
     *
     * @return QueryInfoMessage
     */
    abstract public function getQueryInfoMessage(SmartDidYouMean $smartDidYouMean);

    /**
     * @return Filter[]
     */
    abstract public function getFilters();
}
