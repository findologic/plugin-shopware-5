<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ResponseParser;

use Enlight_Controller_Request_RequestHttp;
use Enlight_Exception;
use Exception;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Promotion;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\CategoryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\DefaultInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\SearchTermQueryInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage\VendorInfoMessage;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\SmartDidYouMean;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21ResponseParser;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;

class Xml21ResponseParserTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    public function findologicResponseProvider()
    {
        return [
            'No smart-did-you-mean tags present' => [
                'demoResponseWithoutQuery.xml',
                null,
                null
            ],
            'Type is did-you-mean' => [
                'demoResponseWithDidYouMeanQuery.xml',
                'did-you-mean',
                '?search=ps4&forceOriginalQuery=1'
            ],
            'Type is improved' => [
                'demoResponseWithImprovedQuery.xml',
                'improved',
                '?search=ps4&forceOriginalQuery=1'
            ],
            'Type is corrected' => [
                'demoResponseWithCorrectedQuery.xml',
                'corrected',
                null
            ],
            'Type is forced' => [
                'demoResponseWithOriginalQueryType.xml',
                'forced',
                null
            ]
        ];
    }

    /**
     * @dataProvider findologicResponseProvider
     *
     * @param string $filename
     * @param string $expectedType
     * @param string|null $expectedLink
     *
     * @throws Exception
     */
    public function testSmartDidYouMean(
        $filename,
        $expectedType,
        $expectedLink
    ) {
        $response = Utility::getDemoResponse($filename);
        $responseParser = ResponseParser::getInstance($response);
        $smartDidYouMean = $responseParser->getSmartDidYouMean();

        $this->assertInstanceOf(SmartDidYouMean::class, $smartDidYouMean);
        $this->assertSame($expectedType, $smartDidYouMean->getType());
        $this->assertSame($expectedLink, $smartDidYouMean->getLink());
    }

    public function testProductsWhenResponseDoesNotHaveProducts()
    {
        $response = Utility::getDemoResponse('demoResponseWithNoResults.xml');
        $responseParser = ResponseParser::getInstance($response);
        $products = $responseParser->getProducts();
        $this->assertEmpty($products);
    }

    public function testProductsWhenResponseContainsOneProduct()
    {
        $article = Utility::createTestProduct(1, true);
        $xmlResponse = Utility::getDemoXML('demoResponseWithOneProduct.xml');
        $xmlResponse->products->product[0]->attributes()->id = $article->getId();

        $response = new Xml21Response($xmlResponse->asXML());
        $responseParser = ResponseParser::getInstance($response);
        $products = $responseParser->getProducts();
        $this->assertCount(1, $products);

        foreach ($products as $product) {
            $this->assertEquals($product['orderNumber'], $article->getMainDetail()->getNumber());
            $this->assertEquals($product['detailId'], $article->getMainDetail()->getId());
        }
    }

    public function testProductsWhenResponseContainsOneProductThatDoesNotExist()
    {
        $response = Utility::getDemoResponse('demoResponseWithOneProduct.xml');
        $responseParser = ResponseParser::getInstance($response);
        $products = $responseParser->getProducts();
        $this->assertEmpty($products);
    }

    public function testResponseWhenLandingPageExists()
    {
        $response = Utility::getDemoResponse('demoResponseWithLandingPage.xml');
        $responseParser = ResponseParser::getInstance($response);
        $uri = $responseParser->getLandingPageUri();
        $this->assertNotNull($uri);
        $this->assertSame('https://blubbergurken.io', $uri);
    }

    public function testResponseWhenLandingPageDoesNotExist()
    {
        $response = Utility::getDemoResponse('demoResponse.xml');
        $responseParser = ResponseParser::getInstance($response);
        $uri = $responseParser->getLandingPageUri();
        $this->assertNull($uri);
    }

    public function testResponseWhenPromotionDoesNotExist()
    {
        $response = Utility::getDemoResponse('demoResponseWithoutPromotion.xml');
        $responseParser = ResponseParser::getInstance($response);
        $promotion = $responseParser->getPromotion();
        $this->assertNull($promotion);
    }

    public function testResponseWhenPromotionExists()
    {
        $response = Utility::getDemoResponse('demoResponseWithPromotion.xml');
        $responseParser = ResponseParser::getInstance($response);
        $promotion = $responseParser->getPromotion();
        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertSame('https://promotion.com/', $promotion->getLink());
        $this->assertSame('https://promotion.com/promotion.png', $promotion->getImage());
    }

    public function queryInfoMessageProvider()
    {
        return [
            'Alternative query is used' => [
                'response' => Utility::getDemoResponse('demoResponseWithAllFilterTypes.xml'),
                'params' => [],
                'expectedInstance' => SearchTermQueryInfoMessage::class,
                'expectedVars' => [
                    'query' => 'ps4',
                    'filterName' => null,
                    'filterValue' => null,
                ]
            ],
            'No alternative query - search query is used' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutAlternativeQuery.xml'),
                'params' => [],
                'expectedInstance' => SearchTermQueryInfoMessage::class,
                'expectedVars' => [
                    'query' => 'ps3',
                    'filterName' => null,
                    'filterValue' => null,
                ]
            ],
            'No search query but selected category' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutQuery.xml'),
                'params' => ['cat' => 'Shoes & More'],
                'expectedInstance' => CategoryInfoMessage::class,
                'expectedVars' => [
                    'query' => null,
                    'filterName' => 'Kategorie',
                    'filterValue' => 'Shoes & More',
                ]
            ],
            'No search query but selected vendor' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutQuery.xml'),
                'params' => ['vendor' => 'Blubbergurken inc.'],
                'expectedInstance' => VendorInfoMessage::class,
                'expectedVars' => [
                    'query' => null,
                    'filterName' => 'Hersteller',
                    'filterValue' => 'Blubbergurken inc.',
                ]
            ],
            'No query and no selected filters' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutQuery.xml'),
                'params' => [],
                'expectedInstance' => DefaultInfoMessage::class,
                'expectedVars' => []
            ],
        ];
    }

    /**
     * @dataProvider queryInfoMessageProvider
     *
     * @param Xml21Response $response
     * @param array $params
     * @param string $expectedInstance
     * @param array $expectedVars
     *
     * @throws Enlight_Exception
     */
    public function testQueryInfoMessageIsReturnedAsExpected(
        Xml21Response $response,
        array $params,
        $expectedInstance,
        array $expectedVars
    ) {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setParams($params);
        Shopware()->Front()->setRequest($request);

        $responseParser = new Xml21ResponseParser($response);
        $queryInfoMessage = $responseParser->getQueryInfoMessage($responseParser->getSmartDidYouMean());
        $this->assertInstanceOf($expectedInstance, $queryInfoMessage);
        $this->assertSame($expectedVars['filterName'], $queryInfoMessage->getFilterName());
        $this->assertSame($expectedVars['filterValue'], $queryInfoMessage->getFilterValue());
        $this->assertSame($expectedVars['query'], $queryInfoMessage->getQuery());
    }
}
