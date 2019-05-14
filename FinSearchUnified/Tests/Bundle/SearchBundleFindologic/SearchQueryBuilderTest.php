<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\RequestException;
use Shopware\Components\HttpClient\Response;
use Shopware\Components\Test\Plugin\TestCase;
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
    protected function setUp()
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

    /**
     * @throws Exception
     */
    public function testAddPriceMethod()
    {
        $price = ['min' => 12.69, 'max' => 42];

        $this->querybuilder->addPrice($price['min'], $price['max']);
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
        $this->querybuilder->addGroup('EK');

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], 'EK');
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('group', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame([$hashed], $parameters['group'], 'Expected usergroup to be hashed correctly');
    }

    /**
     * @throws Exception
     */
    public function testAddGroupMethodWithEmptyCustomerGroup()
    {
        $this->querybuilder->addGroup('');

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], '');
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('group', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame([$hashed], $parameters['group'], 'Expected usergroup to be hashed correctly');
    }

    /**
     * @throws Exception
     */
    public function testAddGroupMethodWithNullCustomerGroup()
    {
        $this->querybuilder->addGroup(null);

        $hashed = StaticHelper::calculateUsergroupHash($this->config['ShopKey'], null);
        $parameters = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('group', $parameters, 'Expected user group to be present in parameters');
        $this->assertSame([$hashed], $parameters['group'], 'Expected usergroup to be hashed correctly');
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

    /**
     * @return array
     */
    public function forceOriginalQueryProvider()
    {
        return [
            'forceOriginalQuery not present' => [null],
            'forceOriginalQuery present and truthy' => [1],
            'forceOriginalQuery present and falsy' => [0]
        ];
    }

    /**
     * @dataProvider forceOriginalQueryProvider
     *
     * @param int|null $forceOriginalQuery
     *
     * @throws Exception
     */
    public function testQuerybuilderForceOriginalQuery($forceOriginalQuery)
    {
        $httpResponse = new Response(200, [], 'alive');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $httpClientMock->expects($this->exactly(2))
            ->method('get')
            ->willReturn($httpResponse);

        $_GET['forceOriginalQuery'] = $forceOriginalQuery;

        $querybuilder = new SearchQueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );

        $querybuilder->execute();
        $parameters = $querybuilder->getParameters();
        $this->assertSame($forceOriginalQuery, $parameters['forceOriginalQuery']);
    }
}
