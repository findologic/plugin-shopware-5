<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware\Components\Test\Plugin\TestCase;

class QueryBuilderTest extends TestCase
{
    /**
     * Data provider for testing scenarios of findologic request execution
     *
     * @return array
     */
    public function querybuilderResponseProvider()
    {
        return [
            'response is successful and body contains "alive"' => [200, 'alive', 'alive'],
            'response is not successful and body is empty' => [404, '', null],
            'response is successful and body is empty' => [200, '', null]
        ];
    }

    /**
     * Data provider for testing scenarios of findologic request execution
     *
     * @return array
     */
    public function querybuilderResponseProviderForException()
    {
        return [
            '"isAlive" returns false because of Exception thrown' => [200, 'alive', false, null],
            '"isAlive" returns true but "execute" throws Exception' => [200, 'alive', true, null],
        ];
    }

    /**
     * @dataProvider querybuilderResponseProviderForException
     *
     * @param int $responseCode
     * @param string|RequestException $responseBody
     * @param bool $expectedIsAlive
     * @param string|null $expectedExecute
     *
     * @throws Exception
     */
    public function testExceptionInFindologicRequest($responseCode, $responseBody, $expectedIsAlive, $expectedExecute)
    {
        $config = Shopware()->Container()->get('config');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response($responseCode, [], $responseBody);
        $this->expectException(Exception::class);
        if (!$expectedIsAlive) {
            $httpClientMock->expects($this->once())
                ->method('get')
                ->will($this->throwException(new Exception()));
        } else {
            $httpClientMock->expects($this->at(0))
                ->method('get')
                ->willReturn($httpResponse);
            $httpClientMock->expects($this->at(1))
                ->method('get')
                ->will($this->throwException(new Exception()));
            $httpClientMock->expects($this->exactly(2))->method('get');
        }
        $querybuilder = new QueryBuilder(
            $httpClientMock,
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            $config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    /**
     * @dataProvider querybuilderResponseProvider
     *
     * @param int $responseCode
     * @param string|RequestException $responseBody
     * @param string|null $expectedExecute
     *
     * @throws Exception
     */
    public function testExecutionOfFindologicRequest($responseCode, $responseBody, $expectedExecute)
    {
        $config = Shopware()->Container()->get('config');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response($responseCode, [], $responseBody);
        $httpClientMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($httpResponse);

        $querybuilder = new QueryBuilder(
            $httpClientMock,
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            $config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    public function querybuilderRequestUrlProvider()
    {
        return [
            '"execute" uses SEARCH_ENDPOINT' => [true, QueryBuilder::SEARCH_ENDPOINT],
            '"execute" uses NAVIGATION_ENDPOINT' => [false, QueryBuilder::NAVIGATION_ENDPOINT],
        ];
    }

    /**
     * @dataProvider querybuilderRequestUrlProvider
     *
     * @param bool $isSearch
     * @param string $endpoint
     *
     * @throws Exception
     */
    public function testQuerybuilderRequestUrl($isSearch, $endpoint)
    {
        $config = Shopware()->Container()->get('config');
        $installerService = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $plugin = $installerService->getPluginByName('FinSearchUnified');
        $shopUrl = rtrim(Shopware()->Shop()->getHost(), '/');
        $parameters = [
            'outputAdapter' => 'XML_2.0',
            'userip' => 'UNKNOWN',
            'revision' => $plugin->getVersion(),
            'shopkey' => $config->offsetGet('ShopKey')
        ];

        $aliveUrl = sprintf(
            '%s/%s/%s?shopkey=%s',
            QueryBuilder::BASE_URL,
            $shopUrl,
            QueryBuilder::ALIVE_ENDPOINT,
            $parameters['shopkey']
        );
        $executeUrl = sprintf(
            '%s/%s/%s?%s',
            QueryBuilder::BASE_URL,
            $shopUrl,
            $endpoint,
            http_build_query($parameters)
        );

        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response(200, [], 'alive');
        $httpClientMock->expects($this->at(0))
            ->method('get')
            ->will($this->returnCallback(function ($url) use ($aliveUrl, $httpResponse) {
                \PHPUnit_Framework_Assert::assertSame($aliveUrl, $url);

                return $httpResponse;
            }));

        $httpClientMock->expects($this->at(1))
            ->method('get')
            ->will($this->returnCallback(function ($url) use ($executeUrl, $httpResponse) {
                \PHPUnit_Framework_Assert::assertSame($executeUrl, $url);

                return $httpResponse;
            }));

        $httpClientMock->expects($this->exactly(2))->method('get');

        $querybuilder = new QueryBuilder(
            $httpClientMock,
            $installerService,
            $config
        );

        $querybuilder->execute($isSearch);
    }
}
