<?php

namespace FinSearchUnified\Components;

use PHPUnit\Framework\Assert;
use ReflectionException;
use ReflectionObject;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware\Components\Test\Plugin\TestCase;
use Zend_Cache_Core;

class ConfigLoaderTest extends TestCase
{
    /**
     * @var string
     */
    protected $expectedUrl;

    protected function setUp()
    {
        parent::setUp();

        $this->expectedUrl = sprintf(
            '%s%s%s',
            'https://cdn.findologic.com/static',
            strtoupper(md5(Shopware()->Config()->offsetGet('ShopKey'))),
            'config.json'
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testConfigUrl()
    {
        $configLoader = new ConfigLoader(
            Shopware()->Container()->get('cache'),
            Shopware()->Container()->get('http_client'),
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('getUrl');
        $method->setAccessible(true);
        $url = $method->invoke($configLoader);

        $this->assertSame($this->expectedUrl, $url, 'Incorrect url was returned');
    }

    /**
     * @dataProvider configFileProvider
     *
     * @param int $responseCode
     * @param string|null $responseBody
     * @param string|null $expectedResponse
     * @param string $assertionMessage
     *
     * @throws ReflectionException
     */
    public function testConfigFile($responseCode, $responseBody, $expectedResponse, $assertionMessage)
    {
        $httpResponse = new Response($responseCode, [], $responseBody);
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        if ($responseBody === null) {
            $httpClientMock->expects($this->once())
                ->method('get')
                ->willThrowException(new RequestException());
        } else {
            $callback = $this->returnCallback(function ($url) use ($httpResponse) {
                Assert::assertSame($this->expectedUrl, $url);

                return $httpResponse;
            });

            $httpClientMock->expects($this->once())
                ->method('get')
                ->will($this->onConsecutiveCalls($callback));
        }

        $configLoader = new ConfigLoader(
            Shopware()->Container()->get('cache'),
            $httpClientMock,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('getConfigFile');
        $method->setAccessible(true);
        $configBody = $method->invoke($configLoader);

        $this->assertSame($expectedResponse, $configBody, $assertionMessage);
    }

    public function configFileProvider()
    {
        return [
            'Correct url is provided' => [200, 'configBody', 'configBody', 'Expected config url to be correct'],
            'Url request throws exception' => [200, null, null, 'Expected config to be null'],
            'Url request is not successful' => [404, 'configBody', null, 'Expected config to be null'],
            'Url request is successful' => [200, 'configBody', 'configBody', 'Expected config file to be returned'],
        ];
    }

    public function testCacheKey()
    {
        $configLoader = new ConfigLoader(
            Shopware()->Container()->get('cache'),
            Shopware()->Container()->get('http_client'),
            Shopware()->Config()
        );

        $expectedCacheKey =
            sprintf('%s_%s', 'fin_service_config', strtoupper(Shopware()->Config()->offsetGet('ShopKey')));

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('getCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($configLoader);

        $this->assertSame($expectedCacheKey, $key, 'Incorrect cache key was returned');
    }

    /**
     * @dataProvider warmUpCacheProvider
     *
     * @param int $responseCode
     * @param string|null $expectedConfig
     *
     * @throws ReflectionException
     */
    public function testWarmUpCache($responseCode, $responseBody, $expectedConfig)
    {
        $httpResponse = new Response($responseCode, [], $responseBody);
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        $responseCallback = $this->returnCallback(function ($url) use ($httpResponse) {
            Assert::assertSame($this->expectedUrl, $url);

            return $httpResponse;
        });

        $httpClientMock->expects($this->once())
            ->method('get')
            ->will($this->onConsecutiveCalls($responseCallback));

        $mockedCache = $this->createMock(Zend_Cache_Core::class);
        $args = [
            $expectedConfig,
            sprintf('%s_%s', 'fin_service_config', strtoupper(Shopware()->Config()->offsetGet('ShopKey'))),
            ['FINDOLOGIC'],
            84600

        ];

        if (empty($expectedConfig)) {
            $mockedCache->expects($this->never())->method('save');
        } else {
            $cacheCallback = $this->returnCallback(function () use ($args) {
                Assert::assertSame($args, func_get_args());

                return true;
            });

            $mockedCache->expects($this->once())->method('save')->will($this->onConsecutiveCalls($cacheCallback));
        }

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClientMock,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('warmUpCache');
        $method->setAccessible(true);
        $method->invoke($configLoader);
    }

    public function warmUpCacheProvider()
    {
        return [
            'Config file is empty or null' => [200, null, null],
            'Config file returns extra parameters' => [
                200,
                json_encode([
                    'isStagingShop' => false,
                    'directIntegration' => ['enabled' => false, 'iShouldNotBeHere' => true]
                ]),
                ['isStagingShop' => false, 'directIntegration' => ['enabled' => false]]
            ],
        ];
    }
}
