<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use FinSearchUnified\Subscriber\Widgets;
use ReflectionException;
use Shopware\Components\Routing\Matchers\RewriteMatcher;
use Shopware_Controllers_Widgets_Listing;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class WidgetsTest extends SubscriberTestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Shopware()->Container()->get('config_writer')->save('ActivateFindologic', true);
    }

    protected function tearDown()
    {
        unset(Shopware()->Session()->isSearchPage, Shopware()->Session()->isCategoryPage);
        parent::tearDown();
    }

    /**
     * @dataProvider searchParameterProvider
     *
     * @param array $requestParameters
     * @param array $expectedRequestParameters
     * @param string $expectedMessage
     *
     * @throws ReflectionException
     */
    public function testBeforeListingCountActionIfFindologicSearchIsActive(
        array $requestParameters,
        array $expectedRequestParameters,
        $expectedMessage
    ) {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets')
            ->setParams($requestParameters);

        // Make sure that the findologic search is triggered
        Shopware()->Container()->get('front')->setRequest($request);
        Shopware()->Session()->isSearchPage = true;

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        // Create mocked args for getting Subject and Request due to backwards compatibility
        $args = $this->createMock(Enlight_Hook_HookArgs::class);
        $args->expects($this->once())->method('getSubject')->willReturn($subject);

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->beforeListingCountAction($args);

        $params = $subject->Request()->getParams();

        if (empty($expectedRequestParameters)) {
            $this->assertArrayNotHasKey('sSearch', $params, 'Expected no query parameter to be set');
        } else {
            $this->assertSame($expectedRequestParameters, $params, $expectedMessage);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testBeforeListingCountActionIfShopSearchIsActive()
    {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')->setActionName('listingCount')->setModuleName('widgets');

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        // Create mocked args for getting Subject and Request due to backwards compatibility
        $args = $this->createMock(Enlight_Hook_HookArgs::class);
        $args->expects($this->never())->method('getSubject');

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->beforeListingCountAction($args);

        $params = $subject->Request()->getParams();

        $this->assertArrayNotHasKey('sSearch', $params, 'Expected no query parameter to be set');
        $this->assertArrayNotHasKey('sCategory', $params, 'Expected no category parameter to be set');
    }

    public function searchParameterProvider()
    {
        return [
            'Only category ID 5 was requested' => [
                ['sCategory' => 5],
                [],
                'Expected only "sCategory" to be present'
            ],
            'Only search term "blubbergurke" was requested' => [
                ['sSearch' => 'blubbergurke'],
                ['sSearch' => 'blubbergurke'],
                'Expected only "sSearch" parameter to be present'
            ],
            'Neither search nor category listing was requested' => [
                [],
                ['sSearch' => ' '],
                'Expected "sSearch" to be present with single whitespace character'
            ],
            'Search has an empty string value' => [
                ['sSearch' => ''],
                ['sSearch' => ' '],
                'Expected "sSearch" to be present with single whitespace character'
            ],
        ];
    }

    public function searchPageProvider()
    {
        return [
            'Referer is https://example.com/search/?q=text' => [
                'referer' => 'https://example.com/search/?q=text',
            ],
            'Referer is http://example.com/search/?q=text' => [
                'referer' => 'http://example.com/search/?q=text',
            ],
            'Referer is https://example.com/search?q=text' => [
                'referer' => 'https://example.com/search?q=text',
            ],
            'Referer is https://example.com/search' => [
                'referer' => 'https://example.com/search',
            ],
            'Referer is https://example.com/search/' => [
                'referer' => 'https://example.com/search/',
            ],
            'Referer is https://example.com/shop/search/?q=text' => [
                'referer' => 'https://example.com/shop/search/?q=text',
            ],
            'Referer is https://example.com/shop/search?q=text' => [
                'referer' => 'https://example.com/shop/search?q=text',
            ],
        ];
    }

    /**
     * @dataProvider searchPageProvider
     *
     * @param string $referer
     *
     * @throws ReflectionException
     * @throws Zend_Cache_Exception
     */
    public function testSearchPage($referer)
    {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('widgets')->setParam('sSearch', 'text')->setHeader('referer', $referer);

        $cacheMock = $this->createMock(Zend_Cache_Core::class);
        $cacheMock->expects($this->never())->method('save');
        $cacheMock->expects($this->never())->method('load');

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        $response = new Enlight_Controller_Response_ResponseHttp();
        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request, 'response' => $response]);

        $widget = new Widgets($cacheMock, Shopware()->Container()->get('shopware.routing.matchers.rewrite_matcher'));
        $widget->onWidgetsPreDispatch($args);

        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertTrue($isSearchPage, 'Expected isSearchPage to be true');
        $this->assertFalse($isCategoryPage, 'Expected isCategoryPage to be false');
    }

    public function categoryPageProvider()
    {
        return [
            'Referer is https://example.com/beispiele/?p=1' => [
                'referer' => 'https://example.com/beispiele/?p=1',
                'expectedIsCategoryPage' => true,
            ],
            'Referer is http://example.com/beispiele/?p=1' => [
                'referer' => 'http://example.com/beispiele/?p=1',
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/beispiele?p=1' => [
                'referer' => 'https://example.com/beispiele?p=1',
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/beispiele' => [
                'referer' => 'https://example.com/beispiele',
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/beispiele/' => [
                'referer' => 'https://example.com/beispiele/',
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/shop/beispiele/?p=1' => [
                'referer' => 'https://example.com/shop/beispiele/?p=1',
                'expectedIsCategoryPage' => true,
                'basePath' => 'shop'
            ],
            'Referer is https://example.com/shop/beispiele?p=1' => [
                'referer' => 'https://example.com/shop/beispiele?p=1',
                'expectedIsCategoryPage' => true,
                'basePath' => 'shop'
            ],
            'Referer is https://example.com/i-do-not-exist' => [
                'referer' => 'https://example.com/i-do-not-exist',
                'expectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/i-do-not-exist/' => [
                'referer' => 'https://example.com/i-do-not-exist/',
                'expectedIsCategoryPage' => false,
            ],
        ];
    }

    /**
     * @dataProvider categoryPageProvider
     *
     * @param string $referer
     * @param bool $expectedIsCategoryPage
     * @param string $basePath
     *
     * @throws ReflectionException
     * @throws Zend_Cache_Exception
     */
    public function testCategoryPage($referer, $expectedIsCategoryPage, $basePath = '')
    {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets')
            ->setBasePath($basePath)
            ->setParam('sCategory', 10)
            ->setHeader('referer', $referer);

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        $response = new Enlight_Controller_Response_ResponseHttp();
        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request, 'response' => $response]);

        $cacheMock = $this->createMock(Zend_Cache_Core::class);
        $cacheMock->expects($this->once())->method('load')->willReturn(false);
        $cacheMock->expects($this->once())->method('save')->willReturn(true);

        $widget = new Widgets($cacheMock, Shopware()->Container()->get('shopware.routing.matchers.rewrite_matcher'));
        $widget->onWidgetsPreDispatch($args);

        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertFalse($isSearchPage);
        $this->assertEquals($expectedIsCategoryPage, $isCategoryPage);
    }

    public function homePageProvider()
    {
        return [
            'Referer is https://example.com/' => [
                'referer' => 'https://example.com/',
            ],
            'Referer is https://example.com' => [
                'referer' => 'https://example.com',
            ],
            'Referer is https://example.com/shop/' => [
                'referer' => 'https://example.com/shop/',
            ],
            'Referer is https://example.com/shop' => [
                'referer' => 'https://example.com/shop',
            ]
        ];
    }

    /**
     * @dataProvider homePageProvider
     *
     * @param string $referer
     *
     * @throws ReflectionException
     * @throws Zend_Cache_Exception
     */
    public function testHomePage($referer)
    {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend')->setHeader('referer', $referer);

        $cacheMock = $this->createMock(Zend_Cache_Core::class);
        $cacheMock->expects($this->once())->method('load')->willReturn(false);
        $cacheMock->expects($this->once())->method('save');

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        $response = new Enlight_Controller_Response_ResponseHttp();
        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request, 'response' => $response]);

        $widget = new Widgets($cacheMock, Shopware()->Container()->get('shopware.routing.matchers.rewrite_matcher'));
        $widget->onWidgetsPreDispatch($args);

        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertFalse($isSearchPage, 'Expected isSearchPage to be false');
        $this->assertFalse($isCategoryPage, 'Expected isCategoryPage to be false');
    }

    /**
     * @throws ReflectionException
     * @throws Zend_Cache_Exception
     */
    public function testCacheEntries()
    {
        $referer = 'https://example.com/beispiele/?p=1';
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('widgets')->setParam('sCategory', 5)->setHeader('referer', $referer);

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        $response = new Enlight_Controller_Response_ResponseHttp();
        $args = new Enlight_Event_EventArgs(['subject' => $subject, 'request' => $request, 'response' => $response]);

        $cacheMock = $this->createMock(Zend_Cache_Core::class);
        $cacheMock->expects($this->never())->method('save');
        $cacheMock->expects($this->once())->method('load')->willReturn(true);

        $rewriteMatcherMock = $this->createMock(RewriteMatcher::class);
        $rewriteMatcherMock->expects($this->never())->method('match');

        $widget = new Widgets($cacheMock, $rewriteMatcherMock);
        $widget->onWidgetsPreDispatch($args);

        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertFalse($isSearchPage);
        $this->assertTrue($isCategoryPage);
    }
}
