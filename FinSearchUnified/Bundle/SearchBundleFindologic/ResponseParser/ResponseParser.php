<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\QueryInfoMessage;
use InvalidArgumentException;

abstract class ResponseParser
{
    /**
     * @var Xml21Response
     */
    protected $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @return Xml21ResponseParser
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
}
