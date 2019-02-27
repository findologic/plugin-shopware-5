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
    /** Data provider for testing scenarios of findologic request execution
     *
     * @return array
     */
    public function executionProvider()
    {
        return [
            'response is successful and body contains "alive"' => [200, 'alive', true, 'alive'],
            'response is not successful and body is empty' => [404, '', false, null],
            'response is successful and body is empty' => [200, '', false, null],
            '`isAlive` returns false because of Exception thrown' => [200, 'alive', false, null],
            '`isAlive` returns true but `execute` throws Exception' => [200, 'alive', true, null],
        ];
    }

    /**
     * @dataProvider executionProvider
     *
     * @param int $responseCode
     * @param string|RequestException $responseBody
     * @param bool $expectedIsAlive
     * @param string|null $expectedExecute
     *
     * @throws Exception
     */
    public function testExecutionOfFindologicRequest($responseCode, $responseBody, $expectedIsAlive, $expectedExecute)
    {
        $config = Shopware()->Container()->get('config');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response($responseCode, [], $responseBody);
        if ($responseBody === 'alive') {
            if (!$expectedIsAlive) {
                $this->expectException(Exception::class);
                $httpClientMock->expects($this->once())
                    ->method('get')
                    ->will($this->throwException(new Exception()));
            } else {
                if ($expectedExecute === null) {
                    $this->expectException(Exception::class);
                    $httpClientMock->expects($this->at(0))
                        ->method('get')
                        ->willReturn($httpResponse);
                    $httpClientMock->expects($this->at(1))
                        ->method('get')
                        ->will($this->throwException(new Exception()));
                    $httpClientMock->expects($this->exactly(2))->method('get');
                } else {
                    $httpClientMock->expects($this->exactly(2))->method('get')->willReturn($httpResponse);
                }
            }
        } else {
            $httpClientMock->expects($this->atLeastOnce())
                ->method('get')
                ->willReturn($httpResponse);
        }
        $querybuilder = new QueryBuilder(
            $httpClientMock,
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            $config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    public function querybuilderRequestURLProvider()
    {
        return [
            '`execute` uses SEARCH_ENDPOINT' => [true, UrlBuilder::SEARCH_ENDPOINT],
            '`execute` uses NAVIGATION_ENDPOINT' => [false, UrlBuilder::NAVIGATION_ENDPOINT],
        ];
    }

    /**
     * @dataProvider querybuilderRequestURLProvider
     *
     * @param bool $isSearch
     * @param string $endpoint
     *
     * @throws Exception
     */
    public function testQuerybuilderRequestURL($isSearch, $endpoint)
    {
        $config = Shopware()->Container()->get('config');
        $installerService = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $plugin = $installerService->getPluginByName('FinSearchUnified');
        $shopUrl = (rtrim(Shopware()->Shop()->getHost(), '/') . '/');
        $parameters = [
            'userip' => 'UNKNOWN',
            'revision' => $plugin->getVersion(),
            'shopkey' => $config->offsetGet('ShopKey')
        ];

        $executeUrl = sprintf(
            '%s%s%s?%s',
            UrlBuilder::BASE_URL,
            $shopUrl,
            $endpoint,
            http_build_query($parameters)
        );

        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $httpResponse = new Response(200, [], 'alive');
        $httpClientMock->expects($this->at(0))
            ->method('get')
            ->willReturn($httpResponse);

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
