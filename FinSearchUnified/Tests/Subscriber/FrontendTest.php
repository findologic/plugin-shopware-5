<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use PHPUnit\Framework\Assert;
use ReflectionException;
use Shopware_Controllers_Frontend_Media;
use Shopware_Controllers_Widgets_Listing;

class FrontendTest extends SubscriberTestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Session()->offsetUnset('isCategoryPage');
        Shopware()->Session()->offsetUnset('isSearchPage');
        $_GET = [];
    }

    /**
     * @return array
     */
    public function frontendPreDispatchProvider()
    {
        return [
            'Search Page' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'search',
                'sAction' => 'index'
            ],
            'Category Page' => [
                'sSearch' => null,
                'sCategory' => 5,
                'sController' => 'listing',
                'sAction' => 'index'
            ],
            'Manufacturer Page' => [
                'sSearch' => null,
                'sCategory' => null,
                'sController' => 'listing',
                'sAction' => 'manufacturer'
            ],
            'Current Page is not Listing or Search' => [
                'sSearch' => null,
                'sCategory' => null,
                'sController' => 'index',
                'sAction' => 'index'
            ]
        ];
    }

    /**
     * @return array
     */
    public function ajaxCartRequestProvider()
    {
        return [
            'Add article to cart' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'checkout',
                'sAction' => 'ajaxAddArticleCart'
            ],
            'Remove article from cart' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'checkout',
                'sAction' => 'ajaxDeleteArticleCart'
            ],
            'Load cart content' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'checkout',
                'sAction' => 'ajaxCart'
            ],
            'Get current cart amount' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'checkout',
                'sAction' => 'ajaxAmount'
            ]
        ];
    }

    public function listingCountConditionProvider()
    {
        return [
            'Check values after listingCount call on Search Page' => [
                'isSearch' => true,
                'isCategory' => false
            ],
            'Check values after listingCount call in Category Page' => [
                'isSearch' => false,
                'isCategory' => true
            ]
        ];
    }

    public function vendorProvider()
    {
        return [
            'Regular vendor name' => ['Brand'],
            'Vendor name containing whitespace' => ['Awesome+Brand'],
            'Vendor name containing "+" character' => ['Brand%2BFriend'],
            'Vendor name containing special characters' => ['s.%C3%96liver'],
        ];
    }

    public function legacyUrlProvider()
    {
        return [
            'Query without filters' => [
                'params' => [
                    'controller' => 'FinSearchAPI',
                    'action' => 'search',
                    'sSearch' => 'test'
                ],
                'expectedUrl' => '/search?sSearch=test'
            ],
            'Query with filter' => [
                'params' => [
                    'controller' => 'FinSearchAPI',
                    'action' => 'search',
                    'sSearch' => 'test',
                    'attrib' => ['vendor' => ['Shopware']],
                ],
                'expectedUrl' => '/search?' . http_build_query([
                        'sSearch' => 'test',
                        'attrib' => ['vendor' => ['Shopware']],
                    ], null, '&', PHP_QUERY_RFC3986)
            ],
            'Query with filter and special characters' => [
                'params' => [
                    'controller' => 'FinSearchAPI',
                    'action' => 'search',
                    'sSearch' => 'test',
                    'attrib' => ['vendor' => ['Shopware / Österreich#%']],
                ],
                'expectedUrl' => '/search?' . http_build_query([
                        'sSearch' => 'test',
                        'attrib' => ['vendor' => ['Shopware / Österreich#%']],
                    ], null, '&', PHP_QUERY_RFC3986)
            ],
            'Lowercase controller' => [
                'params' => [
                    'controller' => 'finsearchapi',
                    'action' => 'search',
                    'sSearch' => 'test'
                ],
                'expectedUrl' => null
            ],
            'Uppercase controller' => [
                'params' => [
                    'controller' => 'FINSEARCHAPI',
                    'action' => 'search',
                    'sSearch' => 'test'
                ],
                'expectedUrl' => null
            ],
            'Random controller' => [
                'params' => [
                    'controller' => 'fInsEarCHApI',
                    'action' => 'search',
                    'sSearch' => 'test'
                ],
                'expectedUrl' => null
            ],
        ];
    }

    /**
     * @dataProvider frontendPreDispatchProvider
     * @dataProvider ajaxCartRequestProvider
     *
     * @param string $sSearch
     * @param int|null $sCategory
     * @param string $sController
     * @param string $sAction
     */
    public function testFrontendPreDispatchConditions($sSearch, $sCategory, $sController, $sAction)
    {
        $isSearch = isset($sSearch);
        $isCategory = isset($sCategory);

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName($sController)
            ->setActionName($sAction)
            ->setModuleName('frontend');

        if ($isSearch) {
            $request->setParam('sSearch', $sSearch);
        } else {
            $request->setParam('sCategory', $sCategory);
        }

        // Create mocked args for getting Subject and Request
        $args = $this->createMock(Enlight_Event_EventArgs::class);
        $args->method('get')->with('request')->willReturn($request);

        Shopware()->Session()->isCategoryPage = $isCategory;
        Shopware()->Session()->isSearchPage = $isSearch;

        $frontend = Shopware()->Container()->get('fin_search_unified.subscriber.frontend');
        $frontend->onFrontendPreDispatch($args);

        // Check session values after FrontendPreDispatch Call
        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertEquals(
            $isSearch,
            $isSearchPage,
            sprintf('Expected isSearchPage to be %s', $isSearch ? 'true' : 'false')
        );
        $this->assertEquals(
            $isCategory,
            $isCategoryPage,
            sprintf('Expected isCategoryPage to be %s', $isCategory ? 'true' : 'false')
        );
    }

    /**
     * @dataProvider listingCountConditionProvider
     *
     * @param bool $isSearch
     * @param bool $isCategory
     *
     * @throws ReflectionException
     */
    public function testSessionValuesAfterListingCount($isSearch, $isCategory)
    {
        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets');

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request]);

        Shopware()->Session()->isCategoryPage = $isCategory;
        Shopware()->Session()->isSearchPage = $isSearch;

        $frontend = Shopware()->Container()->get('fin_search_unified.subscriber.frontend');
        $frontend->onFrontendPreDispatch($args);

        // Check session values after FrontendPreDispatch Call
        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertEquals(
            $isCategory,
            $isCategoryPage,
            'Expected isCategoryPage to remain unchanged after listingCount method call'
        );
        $this->assertEquals(
            $isSearch,
            $isSearchPage,
            'Expected isSearchPage to remain unchanged after listingCount method call'
        );
    }

    /**
     * @dataProvider vendorProvider
     *
     * @param string $vendor
     */
    public function testBeforeSearchIndexAction($vendor)
    {
        Shopware()->Session()->isCategoryPage = null;
        Shopware()->Session()->isSearchPage = true;

        $attrib = [
            'vendor' => [$vendor]
        ];

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName('search')
            ->setActionName('index')
            ->setModuleName('frontend')
            ->setBaseUrl(rtrim(Shopware()->Shop()->getHost(), ' / ') . ' / ')
            ->setParam('attrib', $attrib);

        // Create mocked Subject to be passed in mocked args
        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->setMethods(['Request', 'redirect'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);
        $subject->method('redirect')
            ->with($this->callback(function ($requestUrl) use ($vendor) {
                Assert::assertContains(
                    http_build_query(['vendor' => rawurldecode($vendor)]),
                    $requestUrl
                );

                return true;
            }));

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder(Enlight_Hook_HookArgs::class)
            ->setMethods(['getSubject', 'getRequest'])
            ->disableOriginalConstructor()
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

        $frontend = Shopware()->Container()->get('fin_search_unified.subscriber.frontend');
        $frontend->beforeSearchIndexAction($args);
    }

    /**
     * @dataProvider legacyUrlProvider
     *
     * @param array $params
     * @param string $expectedUrl
     */

    public function testLegacyUrls(array $params, $expectedUrl)
    {
        $queryParams = $params;
        unset($queryParams['controller'], $queryParams['action']);

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName($params['controller'])
            ->setActionName($params['action'])
            ->setQuery($queryParams)
            ->setBaseUrl(rtrim(Shopware()->Shop()->getHost(), ' / '));

        // Set request URI for legacy search
        if ($params['controller'] === 'FinSearchAPI') {
            $request->setRequestUri('/FinSearchAPI/search');
        }

        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->createMock(Enlight_Controller_Response_ResponseHttp::class);
        if ($expectedUrl === null) {
            $response->expects($this->never())->method('setRedirect');
        } else {
            $response->expects($this->once())->method('setRedirect')->with($expectedUrl, 301);
        }

        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request, 'response' => $response]);

        $frontend = Shopware()->Container()->get('fin_search_unified.subscriber.frontend');
        $frontend->onRouteStartup($args);
    }

    /**
     * @throws ReflectionException
     */
    public function testMediaRequestDoesNotResetPageFlags()
    {
        Shopware()->Session()->isSearchPage = true;
        Shopware()->Session()->isCategoryPage = false;

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName('media')
            ->setActionName('fallback')
            ->setModuleName('frontend');

        $subject = $this->getControllerInstance(Shopware_Controllers_Frontend_Media::class, $request);

        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request]);

        $frontend = Shopware()->Container()->get('fin_search_unified.subscriber.frontend');
        $frontend->onFrontendPreDispatch($args);

        // Check session values after FrontendPreDispatch Call
        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertTrue($isSearchPage, "Expected isSearchPage to remain 'true' after media request");
        $this->assertFalse($isCategoryPage, "Expected isCategoryPage to remain 'false' after media request");
    }
}
