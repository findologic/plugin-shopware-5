<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use FinSearchUnified\Tests\TestCase;
use Shopware_Components_Config;

class SearchQueryBuilderTest extends TestCase
{
    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var InstallerService
     */
    private $installerService;

    /**
     * @var SearchQueryBuilder
     */
    private $querybuilder;

    /**
     * @throws Exception
     */
    protected function setUp():void
    {
        parent::setUp();

        $this->config = Shopware()->Container()->get('config');
        $this->installerService = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');

        $this->querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            $this->installerService,
            $this->config
        );
    }

    protected function tearDown():void
    {
        parent::tearDown();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            unset($_SERVER['HTTP_CLIENT_IP']);
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
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

        $querybuilder = new SearchQueryBuilder(
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

        $querybuilder = new SearchQueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedExecute, $response);
    }

    /**
     * @dataProvider searchTermProvider
     *
     * @param string $searchTerm
     * @param string $expectedResult
     *
     * @throws Exception
     */
    public function testAddQueryMethod($searchTerm, $expectedResult)
    {
        $this->querybuilder->addQuery($searchTerm);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('query', $parameters);
        $this->assertSame($expectedResult, $parameters['query'], sprintf('Expected query to be "%s"', $expectedResult));
    }

    public function queryBuilderAddFlagDataProvider()
    {
        return [
            'Value set' => [
                true,
                true
            ],
            'Value false' => [
                false,
                false
            ],
            'Value null' => [
                null,
                false
            ],
            'Value empty string' => [
                '',
                false
            ],
            'Value is non empty string' => [
                'non empty string',
                true
            ]
        ];
    }

    /**
     * @dataProvider queryBuilderAddFlagDataProvider
     *
     * @param mixed $value The Flag value
     * @param bool $expected The expected outcome
     */
    public function testQueryBuilderAddFlag($value, $expected)
    {
        $this->querybuilder->addFlag('forceOriginalQuery', $value);
        $parameters = $this->querybuilder->getParameters();
        $this->assertEquals($parameters['forceOriginalQuery'], $expected);
    }

    /**
     * @throws Exception
     */
    public function testAddRangeFilterMethod()
    {
        $price = ['min' => 12.69, 'max' => 42];

        $this->querybuilder->addRangeFilter('price', $price['min'], $price['max']);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $parameters);
        $this->assertArrayHasKey('price', $parameters['attrib']);
        $this->assertEquals(
            $price,
            $parameters['attrib']['price'],
            '"price" parameter does not match the given arguments'
        );
    }

    /**
     * @throws Exception
     */
    public function testAddOrderMethod()
    {
        $this->querybuilder->addOrder('price ASC');
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('order', $parameters);
        $this->assertSame('price ASC', $parameters['order'], 'Expected order to be "price ASC"');
    }

    /**
     * @dataProvider vendorFilterProvider
     *
     * @param array $filters
     * @param array $expectedFilters
     *
     * @throws Exception
     */
    public function testAddFilterMethod(array $filters, array $expectedFilters)
    {
        foreach ($filters as $filter) {
            $this->querybuilder->addFilter('vendor', $filter);
        }

        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $parameters);
        $this->assertArrayHasKey('vendor', $parameters['attrib']);

        $this->assertEquals(
            $expectedFilters,
            $parameters['attrib']['vendor'],
            'Expected filters to have correct values'
        );
    }

    /**
     * @throws Exception
     */
    public function testAddCategoriesMethod()
    {
        $categories = ['Genusswelten', 'Sommerwelten'];
        $this->querybuilder->addCategories($categories);

        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $parameters);
        $this->assertArrayHasKey('cat', $parameters['attrib']);

        $this->assertEquals(
            $categories,
            $parameters['attrib']['cat'],
            'Expected both categories to be available in parameters'
        );
    }

    /**
     * @throws Exception
     */
    public function testSetFirstResultMethod()
    {
        $this->querybuilder->setFirstResult(0);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('first', $parameters);
        $this->assertSame(0, $parameters['first'], 'Expected offset to be 0');
    }

    /**
     * @throws Exception
     */
    public function testMaxResultMethod()
    {
        $this->querybuilder->setMaxResults(12);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('count', $parameters);
        $this->assertSame(12, $parameters['count'], 'Expected limit to be 12');
    }

    /**
     * @throws Exception
     */
    public function testAddGroupMethodWithCustomerGroup()
    {
        $this->querybuilder->addUserGroup('EK');

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], 'EK');
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('usergrouphash', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame($hashed, $parameters['usergrouphash'], 'Expected usergroup to be hashed correctly');
    }

    /**
     * @throws Exception
     */
    public function testAddGroupMethodWithEmptyCustomerGroup()
    {
        $this->querybuilder->addUserGroup('');

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], '');
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('usergrouphash', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame($hashed, $parameters['usergrouphash'], 'Expected usergroup to be hashed correctly');
    }

    /**
     * @throws Exception
     */
    public function testAddGroupMethodWithNullCustomerGroup()
    {
        $this->querybuilder->addUserGroup(null);

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], null);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('usergrouphash', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame($hashed, $parameters['usergrouphash'], 'Expected usergroup to be hashed correctly');
    }

    public function searchTermProvider()
    {
        return [
            'Query is "search"' => ['search', 'search'],
            'Query is "search+term"' => ['search+term', 'search term'],
            'Query is "search.term"' => ['search.term', 'search.term'],
            'Query is "search%2Bterm"' => ['search%2Bterm', 'search+term'],
            'Query is "search%2Fterm"' => ['search%2Fterm', 'search/term'],
        ];
    }

    public function vendorFilterProvider()
    {
        return [
            'Vendor is "Brands%2BFriends' => [
                ['Brands%2BFriends'],
                ['Brands+Friends']
            ],
            'Two vendors "Brands" and "Friends"' => [
                ['Brands', 'Friends'],
                ['Brands', 'Friends'],
            ]
        ];
    }

    /**
     * @dataProvider querybuilderResponseProvider
     *
     * @param int $responseCode
     * @param int $callCount
     * @param string|null $expectedResponse
     *
     * @throws Exception
     */
    public function testQueryBuilderResponse($responseCode, $callCount, $expectedResponse)
    {
        $httpResponse = new Response($responseCode, [], 'alive');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $httpClientMock->expects($this->exactly($callCount))
            ->method('get')
            ->willReturn($httpResponse);

        $querybuilder = new SearchQueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );
        $response = $querybuilder->execute();
        $this->assertSame($expectedResponse, $response);
    }

    public function querybuilderResponseProvider()
    {
        return [
            'Response is 200' => [200, 2, 'alive'],
            'Response is 500' => [500, 1, null],
        ];
    }

    public function testOutputAdapterIsExplicitlySetToXml()
    {
        $parameters = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('outputAdapter', $parameters);
        $this->assertEquals('XML_2.0', $parameters['outputAdapter']);
    }

    /**
     * @param string $field
     * @param string $ipAddress The ip address to set.
     */
    private function setIpHeader($field, $ipAddress)
    {
        $_SERVER[$field] = $ipAddress;
    }

    /**
     * Scenarios of IPs which should be filtered
     *
     * @return array Cases with the value to be filtered and the expected return value.
     */
    public function ipAddressProvider()
    {
        return [
            'Single IP' => ['192.168.0.1', '192.168.0.1'],
            'Same IP twice separated by comma' => ['192.168.0.1,192.168.0.1', '192.168.0.1'],
            'Same IP twice separated by comma and space' => ['192.168.0.1, 192.168.0.1', '192.168.0.1'],
            'Different IPs separated by comma' => ['192.168.0.1,10.10.0.200', '192.168.0.1,10.10.0.200'],
            'Different IPs separated by comma and space' => ['192.168.0.1, 10.10.0.200', '192.168.0.1,10.10.0.200']
        ];
    }

    /**
     * Scenarios of proxy IPs which should be filtered
     *
     * @return array
     */
    public function reverseProxyIpAddressProvider()
    {
        return [
            'Single IP' => ['192.168.0.1'],
            'Same IP twice separated by comma' => ['192.168.0.1,192.168.0.1'],
            'Same IP twice separated by comma and space' => ['192.168.0.1, 192.168.0.1'],
            'Different IPs separated by comma' => ['192.168.0.1,10.10.0.200'],
            'Different IPs separated by comma and space' => ['192.168.0.1, 10.10.0.200']
        ];
    }

    /**
     * @dataProvider ipAddressProvider
     *
     * @param string $unfilteredIp
     * @param string $expectedValue
     *
     * @throws Exception
     */
    public function testSendOnlyUniqueUserIps($unfilteredIp, $expectedValue)
    {
        $this->setIpHeader('HTTP_CLIENT_IP', $unfilteredIp);

        $querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            $this->installerService,
            $this->config
        );

        $parameters = $querybuilder->getParameters();

        $this->assertArrayHasKey('userip', $parameters);
        $this->assertEquals($expectedValue, $parameters['userip']);
    }

    /**
     * @dataProvider reverseProxyIpAddressProvider
     *
     * @param string $unfilteredIp
     *
     * @throws Exception
     */
    public function testSendsOnlyClientIpFromReverseProxy($unfilteredIp)
    {
        $this->setIpHeader('HTTP_X_FORWARDED_FOR', $unfilteredIp);

        $querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            $this->installerService,
            $this->config
        );

        $parameters = $querybuilder->getParameters();

        $this->assertArrayHasKey('userip', $parameters);
        $this->assertEquals('192.168.0.1', $parameters['userip']);
    }

    /**
     * @throws Exception
     */
    public function testHandlesUnknownClientIp()
    {
        $querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            $this->installerService,
            $this->config
        );

        $parameters = $querybuilder->getParameters();

        $this->assertArrayHasKey('userip', $parameters);
        $this->assertEquals('UNKNOWN', $parameters['userip']);
    }
}
