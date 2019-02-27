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
                    $httpClientMock->expects($this->atLeastOnce())->method('get')->willReturn($httpResponse);
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
}
