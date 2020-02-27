<?php

namespace FinSearchUnified\Tests\Components;

use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Tests\TestCase;
use ReflectionException;
use ReflectionObject;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Zend_Cache_Core;

class ConfigLoaderTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
        ],
    ];

    /**
     * @var string
     */
    protected $expectedUrl;

    /**
     * @var mixed
     */
    private $shopkey;

    protected function setUp()
    {
        parent::setUp();

        $this->shopkey = Shopware()->Config()->offsetGet('ShopKey');
        $this->expectedUrl = sprintf(
            '%s/%s/%s',
            'https://cdn.findologic.com/static',
            strtoupper(md5($this->shopkey)),
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

    public function configFileProvider()
    {
        return [
            'Url request throws exception' => [200, null, null, 'Expected config to be null'],
            'Url request is not successful' => [404, 'configBody', null, 'Expected config to be null'],
            'Url request is successful' => [200, 'configBody', 'configBody', 'Expected config file to be returned'],
        ];
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
            $httpClientMock->expects($this->once())
                ->method('get')
                ->with($this->expectedUrl)
                ->willReturn($httpResponse);
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

    public function testCacheKey()
    {
        $configLoader = new ConfigLoader(
            Shopware()->Container()->get('cache'),
            Shopware()->Container()->get('http_client'),
            Shopware()->Config()
        );

        $expectedCacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('getCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($configLoader);

        $this->assertSame($expectedCacheKey, $key, 'Incorrect cache key was returned');
    }

    public function warmUpCacheProvider()
    {
        return [
            'Config file is empty or null' => [200, null, []],
            'Config file returns extra parameters' => [
                200,
                json_encode(
                    [
                        'isStagingShop' => true,
                        'directIntegration' => ['enabled' => false, 'iShouldNotBeHere' => true],
                        'blocks' => ['cat' => false, 'vendor' => true]
                    ]
                ),
                [
                    'isStagingShop' => true,
                    'directIntegration' => ['enabled' => false],
                    'blocks' => ['cat' => false, 'vendor' => true]
                ]
            ],
        ];
    }

    /**
     * @dataProvider warmUpCacheProvider
     *
     * @param int $responseCode
     * @param string|null $responseBody
     * @param array $expectedConfig
     *
     * @throws ReflectionException
     */
    public function testWarmUpCache($responseCode, $responseBody, array $expectedConfig)
    {
        $httpResponse = new Response($responseCode, [], $responseBody);
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $httpClientMock->expects($this->once())
            ->method('get')
            ->with($this->expectedUrl)
            ->willReturn($httpResponse);

        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $cacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);
        $tags = ['FINDOLOGIC'];
        $lifetime = 86400;

        if (empty($expectedConfig)) {
            $mockedCache->expects($this->never())->method('save');
        } else {
            $mockedCache->expects($this->once())
                ->method('save')
                ->with($expectedConfig, $cacheKey, $tags, $lifetime, 8)
                ->willReturn(true);
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

    public function cacheLoadProvider()
    {
        return [
            'Loading cache does not return anything' => [
                false,
                $this->exactly(2)
            ],
            'Loading cache returns cached config' => [
                [
                    'isStagingShop' => false,
                    'directIntegration' => ['enabled' => false],
                    'blocks' => ['cat' => false, 'vendor' => false]
                ],
                $this->once()
            ]
        ];
    }

    /**
     * @dataProvider cacheLoadProvider
     *
     * @param int|bool $cacheTestResponse
     * @param $loadCallCount
     *
     * @throws ReflectionException
     */
    public function testCacheWithCorrectKey($cacheTestResponse, $loadCallCount)
    {
        $config = [
            'isStagingShop' => false,
            'directIntegration' => ['enabled' => false],
            'blocks' => ['cat' => false, 'vendor' => false]
        ];

        $expectedCacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);
        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        if ($cacheTestResponse === false) {
            $mockedCache->expects($this->once())->method('save');

            $httpResponse = new Response(200, [], json_encode($config));
            $httpClient = $this->createMock(GuzzleHttpClient::class);
            $httpClient->expects($this->once())->method('get')->willReturn($httpResponse);
        } else {
            $mockedCache->expects($this->never())->method('save');
            $httpClient = Shopware()->Container()->get('http_client');
        }

        $mockedCache->expects($loadCallCount)
            ->method('load')
            ->with($expectedCacheKey)
            ->willReturnOnConsecutiveCalls($cacheTestResponse, $config);

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClient,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('get');
        $method->setAccessible(true);
        $result = $method->invoke($configLoader, 'directIntegration', 'test');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider cacheLoadProvider
     *
     * @param int|bool $cacheResponse
     * @param $loadCallCount
     *
     * @throws ReflectionException
     */
    public function testCacheWithNonExistingKey($cacheResponse, $loadCallCount)
    {
        $httpClient = Shopware()->Container()->get('http_client');

        $mockedCache = $this->createMock(Zend_Cache_Core::class);
        $mockedCache->expects($this->never())->method('save');
        $mockedCache->expects($loadCallCount)
            ->method('load')
            ->willReturn($cacheResponse);

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClient,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('get');
        $method->setAccessible(true);
        $config = $method->invoke($configLoader, 'iDoNotExist', 'test');

        $this->assertSame('test', $config);
    }

    public function testDirectIntegration()
    {
        $config = [
            'directIntegration' => ['enabled' => false]
        ];

        $expectedCacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);
        $httpClient = Shopware()->Container()->get('http_client');

        $mockedCache = $this->createMock(Zend_Cache_Core::class);
        $mockedCache->expects($this->never())->method('save');
        $mockedCache->expects($this->once())
            ->method('load')
            ->with($expectedCacheKey)
            ->willReturn($config);

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClient,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('directIntegrationEnabled');
        $method->setAccessible(true);
        $result = $method->invoke($configLoader);

        $this->assertFalse($result);
    }

    public function testIsStagingShop()
    {
        $config = [
            'isStagingShop' => false
        ];
        $expectedCacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);
        $httpClient = Shopware()->Container()->get('http_client');

        $mockedCache = $this->createMock(Zend_Cache_Core::class);
        $mockedCache->expects($this->never())->method('save');
        $mockedCache->expects($this->once())
            ->method('load')
            ->with($expectedCacheKey)
            ->willReturn($config);

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClient,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('isStagingShop');
        $method->setAccessible(true);
        $result = $method->invoke($configLoader);

        $this->assertFalse($result);
    }

    public function testSmartSuggestBlocks()
    {
        $config = [
            'blocks' => ['cat' => 'FINDOLOGIC category', 'vendor' => 'FINDOLOGIC vendor']
        ];
        $expectedCacheKey = sprintf('%s_%s', 'fin_service_config', $this->shopkey);
        $httpClient = Shopware()->Container()->get('http_client');

        $mockedCache = $this->createMock(Zend_Cache_Core::class);
        $mockedCache->expects($this->never())->method('save');
        $mockedCache->expects($this->once())
            ->method('load')
            ->with($expectedCacheKey)
            ->willReturn($config);

        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClient,
            Shopware()->Config()
        );

        $reflector = new ReflectionObject($configLoader);
        $method = $reflector->getMethod('getSmartSuggestBlocks');
        $method->setAccessible(true);
        $result = $method->invoke($configLoader);

        $this->assertArrayHasKey('cat', $result);
        $this->assertArrayHasKey('vendor', $result);
        $this->assertSame('FINDOLOGIC category', $result['cat']);
        $this->assertSame('FINDOLOGIC vendor', $result['vendor']);
    }
}
