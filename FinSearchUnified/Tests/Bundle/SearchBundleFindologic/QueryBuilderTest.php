<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware_Components_Config;

class QueryBuilderTest extends TestCase
{
    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var InstallerService
     */
    private $installerService;

    protected function setUp()
    {
        parent::setUp();

        $this->config = Shopware()->Container()->get('config');
        $this->installerService = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
    }

    /**
     * Data provider for testing scenarios of findologic request execution
     *
     * @return array
     */
    public function aliveResponseProvider()
    {
        return [
            'Response is successful and body contains "alive"' => [200, 'alive', 2, 'alive'],
            'Response is not successful and body is empty' => [404, '', 1, null],
            'Response is successful and body is empty' => [200, '', 1, null]
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
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response($responseCode, [], $responseBody);
        $this->expectException(Exception::class);
        if (!$expectedIsAlive) {
            $httpClientMock->expects($this->once())
                ->method('get')
                ->will($this->throwException(new Exception()));
        } else {
            $httpClientMock->expects($this->exactly(2))
                ->method('get')
                ->will($this->onConsecutiveCalls($httpResponse, $this->throwException(new Exception())));
        }
        $querybuilder = new QueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    /**
     * @dataProvider aliveResponseProvider
     *
     * @param int $responseCode
     * @param string $responseBody
     * @param int $callCount
     * @param string|null $expectedExecute
     *
     * @throws Exception
     */
    public function testAliveResponseForFindologicRequest($responseCode, $responseBody, $callCount, $expectedExecute)
    {
        $httpResponse = new Response($responseCode, [], $responseBody);
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $httpClientMock->expects($this->exactly($callCount))
            ->method('get')
            ->willReturn($httpResponse);

        $querybuilder = new QueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    public function querybuilderRequestUrlProvider()
    {
        return [
            'Request Url uses SEARCH_ENDPOINT' => [true, QueryBuilder::SEARCH_ENDPOINT],
            'Request Url uses NAVIGATION_ENDPOINT' => [false, QueryBuilder::NAVIGATION_ENDPOINT],
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
        $plugin = $this->installerService->getPluginByName('FinSearchUnified');
        $shopUrl = rtrim(Shopware()->Shop()->getHost(), '/');
        $parameters = [
            'outputAdapter' => 'XML_2.0',
            'userip' => 'UNKNOWN',
            'revision' => $plugin->getVersion(),
            'shopkey' => $this->config->offsetGet('ShopKey')
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

        $httpResponse = new Response(200, [], 'alive');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $aliveCallback = $this->returnCallback(function ($url) use ($aliveUrl, $httpResponse) {
            \PHPUnit_Framework_Assert::assertSame($aliveUrl, $url);

            return $httpResponse;
        });
        $executeCallback = $this->returnCallback(function ($url) use ($executeUrl, $httpResponse) {
            \PHPUnit_Framework_Assert::assertSame($executeUrl, $url);

            return $httpResponse;
        });
        $httpClientMock->expects($this->exactly(2))
            ->method('get')
            ->will($this->onConsecutiveCalls($aliveCallback, $executeCallback));

        $querybuilder = new QueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );

        $querybuilder->execute($isSearch);
    }
}
