<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware\Components\Test\Plugin\TestCase;

class QueryBuilderTest extends TestCase
{
    public function executionProvider()
    {
        return [
            'response is successful and body contains alive' => [200, 'alive', ''],
            'response is not successful and body is empty' => [404, '', null],
            'response is successful and body is empty' => [200, '', null],
            '`isAlive` returns false because of RequestException' => [200, new RequestException(), null],
            '`isAlive` returns true but `execute` throws RequestException' => [200, 'alive', null],
        ];
    }

    /**
     * @dataProvider executionProvider
     *
     * @param int $responseCode
     * @param string|RequestException $responseBody
     * @param string $expectedResult
     *
     * @throws Exception
     */
    public function testExecutionOfFindologicRequest($responseCode, $responseBody, $expectedResult)
    {
        $shopUrl = (rtrim(Shopware()->Shop()->getHost(), '/') . '/');
        $shopkey = Shopware()->Config()->offsetGet('ShopKey');
        $aliveUrl = sprintf(
            '%s%s%s?shopkey=%s',
            UrlBuilder::BASE_URL,
            $shopUrl,
            UrlBuilder::ALIVE_ENDPOINT,
            $shopkey
        );
        $executeUrl = sprintf(
            '%s%s%s?shopkey=%s',
            UrlBuilder::BASE_URL,
            $shopUrl,
            UrlBuilder::SEARCH_ENDPOINT,
            $shopkey
        );

        $config = Shopware()->Container()->get('config');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        if ($responseBody instanceof RequestException) {
            $this->expectException(RequestException::class);
            $httpResponse = new Response($responseCode, [], 'alive');
        } else {
            $httpResponse = new Response($responseCode, [], $responseBody);
        }
        $httpClientMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($httpResponse);

        $querybuilder = new QueryBuilder(
            $httpClientMock,
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            $config
        );
        try {
            $response = $querybuilder->execute();
        } catch (Exception $e) {
            $this->assertInstanceOf(RequestException::class, $e);
        }
    }
}
