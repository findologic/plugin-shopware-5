<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\QueryBuilder;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NavigationQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware_Components_Config as Config;

class SearchQueryBuilderTest extends TestCase
{
    /**
     * @var InstallerService
     */
    private $installerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installerService = Shopware()->Container()->get('shopware.plugin_manager');
        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getByNamespace', 'get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->any())
            ->method('getByNamespace')
            ->with('FinSearchUnified', 'ShopKey', null)
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $mockConfig->expects($this->any())
            ->method('get')
            ->willReturn(StaticHelper::getShopwareVersion());

        Shopware()->Container()->set('config', $mockConfig);

        // Set default values for test
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testInstanceOfXml21Response()
    {
        $searchNavigationRequest = new SearchRequest();
        $searchNavigationRequest->setQuery('findologic');

        /** @var Client|MockObject $apiClientMock */
        $apiClientMock = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
        $apiClientMock->expects($this->once())->method('send')->willReturn(
            new Xml21Response(Utility::getDemoXML()->asXML())
        );
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            $apiClientMock,
            $searchNavigationRequest
        );
        $response = $queryBuilder->execute();

        $this->assertInstanceOf(Xml21Response::class, $response);
    }

    public function testQueryParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addQuery('findologic');

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('query', $params);
        $this->assertSame('findologic', $params['query']);
    }

    public function testRangeFilterParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addRangeFilter('price', 0.44, 12.00);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $params);
        $this->assertArrayHasKey('price', $params['attrib']);
        $this->assertSame('0.44', $params['attrib']['price']['min']);
        $this->assertSame('12', $params['attrib']['price']['max']);
    }

    public function testFilterParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addFilter('color', 'Red');
        $queryBuilder->addFilter('color', 'Blue');

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $params);
        $this->assertArrayHasKey('color', $params['attrib']);
        $this->assertSame(['Red', 'Blue'], current($params['attrib']['color']));
    }

    public function testPushAttribs()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addPushAttrib('color', 'Red', 0.9);
        $queryBuilder->addPushAttrib('color', 'Blue', 2.7);
        $queryBuilder->addPushAttrib('vendor', 'Shopware', 1.3);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('pushAttrib', $params);
        $this->assertArrayHasKey('color', $params['pushAttrib']);
        $this->assertArrayHasKey('vendor', $params['pushAttrib']);
        $this->assertArrayHasKey('Red', $params['pushAttrib']['color']);
        $this->assertArrayHasKey('Blue', $params['pushAttrib']['color']);
        $this->assertArrayHasKey('Shopware', $params['pushAttrib']['vendor']);
        $this->assertSame(
            [
                'Red' => 0.9,
                'Blue' => 2.7
            ],
            $params['pushAttrib']['color']
        );
        $this->assertSame(
            [
                'Shopware' => 1.3,
            ],
            $params['pushAttrib']['vendor']
        );
    }

    public function testCategoriesParameterForSearch()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addCategories(['Genusswelten', 'Sommerwelten']);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $params);
        $this->assertArrayHasKey('cat', $params['attrib']);
        $this->assertSame('Genusswelten_Sommerwelten', current($params['attrib']['cat']));
    }

    public function testCategoriesParameterForNavigation()
    {
        $searchNavigationRequest = new NavigationRequest();
        $queryBuilder = new NavigationQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $categories = ['Genusswelten', 'Sommerwelten'];
        $queryBuilder->addCategories($categories);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('selected', $params);
        $this->assertArrayHasKey('cat', $params['selected']);
        $this->assertSame('Genusswelten_Sommerwelten', current($params['selected']['cat']));
    }

    public function testOrderParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addOrder('price ASC');

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('order', $params);
        $this->assertSame('price ASC', $params['order']);
    }

    public function testFirstResultParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->setFirstResult(10);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('first', $params);
        $this->assertSame(10, $params['first']);
    }

    public function testMaxResultParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->setMaxResults(10);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('count', $params);
        $this->assertSame(10, $params['count']);
    }

    public function testAddParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addParameter('findologic', 'rocks');

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $params);
        $this->assertArrayHasKey('findologic', $params['attrib']);
        $this->assertSame('rocks', current($params['attrib']['findologic']));
    }

    public function testGetParameter()
    {
        $searchNavigationRequest = new SearchRequest();
        $searchNavigationRequest->addAttribute('findologic', 'is awesome');

        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $attribute = $queryBuilder->getParameter('findologic');
        $this->assertNotNull($attribute);
        $this->assertSame('is awesome', current($attribute));
    }

    public function userGroupProvider()
    {
        return [
            'Correct usergroup is provided' => ['EK'],
            'Empty usergroup is provided' => [''],
            'Usergroup is null' => [null],
        ];
    }

    /**
     * @dataProvider userGroupProvider
     *
     * @param string|null $userGroup
     */
    public function testUserGroupParameterHashedCorrectly($userGroup)
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $usergrouphash = StaticHelper::calculateUsergroupHash(
            Shopware()->Config()->getByNamespace('FinSearchUnified', 'ShopKey'),
            $userGroup
        );
        $queryBuilder->addUserGroup($userGroup);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('usergrouphash', $params);
        $this->assertSame($usergrouphash, current($params['usergrouphash']));
    }

    public function addFlagDataProvider()
    {
        return [
            'Value is set' => [
                true,
                '1'
            ],
            'Value is false' => [
                false,
                '0'
            ],
            'Value is null' => [
                null,
                '0'
            ],
            'Value is empty string' => [
                '',
                '0'
            ],
            'Value is non-empty string' => [
                'non empty string',
                '1'
            ]
        ];
    }

    /**
     * @dataProvider addFlagDataProvider
     *
     * @param $flagValue
     * @param $expectedValue
     */
    public function testAddFlagParameter($flagValue, $expectedValue)
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addFlag('findologic', $flagValue);

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('attrib', $params);
        $this->assertArrayHasKey('findologic', $params['attrib']);
        $this->assertSame($expectedValue, current($params['attrib']['findologic']));
    }

    public function testForceOriginalQueryIsNotTreatedAsFilter()
    {
        $searchNavigationRequest = new SearchRequest();
        $queryBuilder = new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $queryBuilder->addFlag('forceOriginalQuery');

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayNotHasKey('attrib', $params);
        $this->assertArrayHasKey('forceOriginalQuery', $params);

        $this->assertSame(1, $params['forceOriginalQuery']);
    }

    public function testShopTypeParameterIsAddedByDefault()
    {
        $searchNavigationRequest = new SearchRequest();
        new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('shopType', $params);
        $this->assertSame('Shopware5', $params['shopType']);
    }

    public function testShopVersionParameterIsAddedByDefault()
    {
        $searchNavigationRequest = new SearchRequest();
        new SearchQueryBuilder(
            $this->installerService,
            Shopware()->Config(),
            null,
            $searchNavigationRequest
        );

        $params = $searchNavigationRequest->getParams();
        $this->assertArrayHasKey('shopVersion', $params);
        dd($params);
        $this->assertSame('5.7.7', $params['shopVersion']);
    }
}
