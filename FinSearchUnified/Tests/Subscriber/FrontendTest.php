<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Shopware\Components\Test\Plugin\TestCase;

class FrontendTest extends TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        Shopware()->Session()->offsetUnset('isCategoryPage');
        Shopware()->Session()->offsetUnset('isSearchPage');
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
                'sController' => 'index',
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

    public function listingCountConditionProvider()
    {
        return [
            'Check values after listingCount call on Search Page' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'sController' => 'listing',
                'sAction' => 'listingCount'
            ],
            'Check values after listingCount call in Category Page' => [
                'sSearch' => null,
                'sCategory' => 3,
                'sController' => 'listing',
                'sAction' => 'listingCount'
            ]
        ];
    }

    /**
     * @dataProvider frontendPreDispatchProvider
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
            ->setModuleName('frontend')
            ->setParams(['sSearch' => $sSearch, 'sCategory' => $sCategory]);

        // Create mocked Subject to be passed in mocked args
        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder(Enlight_Controller_ActionEventArgs::class)
            ->setMethods(['getSubject', 'getRequest'])
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

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
     * @param string $sSearch
     * @param int|null $sCategory
     * @param string $sController
     * @param string $sAction
     */
    public function testSessionValuesAfterListingCount($sSearch, $sCategory, $sController, $sAction)
    {
        $isSearch = isset($sSearch);
        $isCategory = isset($sCategory);

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName($sController)
            ->setActionName($sAction)
            ->setModuleName('widgets')
            ->setParams(['sSearch' => $sSearch, 'sCategory' => $sCategory]);

        // Create mocked Subject to be passed in mocked args
        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder(Enlight_Controller_ActionEventArgs::class)
            ->setMethods(['getSubject', 'getRequest'])
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

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
            "Expected isCategoryPage to remain unchanged after listingCount method call"
        );
        $this->assertEquals(
            $isSearch,
            $isSearchPage,
            "Expected isSearchPage to remain unchanged after listingCount method call"
        );
    }
}
