<?php

use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\finSearchUnified as Plugin;
use Shopware\Components\Test\Plugin\TestCase;

class FrontendTest extends TestCase
{
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
                'sAction' => 'index',
                'sModule' => 'frontend'
            ],
            'Category Page' => [
                'sSearch' => null,
                'sCategory' => 5,
                'sController' => 'listing',
                'sAction' => 'index',
                'sModule' => 'frontend'
            ],
            'Manufacturer Page' => [
                'sSearch' => null,
                'sCategory' => null,
                'sController' => 'listing',
                'sAction' => 'manufacturer',
                'sModule' => 'frontend'
            ],
            'Backend Module' => [
                'sSearch' => null,
                'sCategory' => null,
                'sController' => 'index',
                'sAction' => 'index',
                'sModule' => 'backend'
            ],
            'Current Page is not Listing or Search' => [
                'sSearch' => null,
                'sCategory' => null,
                'sController' => 'index',
                'sAction' => 'index',
                'sModule' => 'frontend'
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
                'sAction' => 'listingCount',
                'sModule' => 'widgets'
            ],
            'Check values after listingCount call in Category Page' => [
                'sSearch' => null,
                'sCategory' => 3,
                'sController' => 'listing',
                'sAction' => 'listingCount',
                'sModule' => 'widgets'
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
     * @param string $sModule
     */
    public function testFrontendPreDispatchConditions($sSearch, $sCategory, $sController, $sAction, $sModule)
    {
        $isSearch = isset($sSearch);
        $isCategory = isset($sCategory);

        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName($sController)
            ->setActionName($sAction)
            ->setModuleName($sModule)
            ->setParams(['sSearch' => $sSearch, 'sCategory' => $sCategory]);

        // Create mocked Subject to be passed in mocked args
        $subject = $this->getMockBuilder('\Enlight_Controller_Action')
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder('\Enlight_Controller_ActionEventArgs')
            ->setMethods(['getSubject', 'getRequest'])
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $frontend = new \FinSearchUnified\Subscriber\Frontend($plugin->getPath(), new Enlight_Template_Manager());

        $frontend->onFrontendPreDispatch($args);

        // Check session values after FrontendPreDispatch Call
        $isCategoryPage = Shopware()->Session()->offsetGet('isCategoryPage');
        $isSearchPage = Shopware()->Session()->offsetGet('isSearchPage');

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
     * @param string $sModule
     */
    public function testSessionValuesAfterListingCount($sSearch, $sCategory, $sController, $sAction, $sModule)
    {
        // Create Request object to be passed in the mocked Subject
        $request = new RequestHttp();
        $request->setControllerName($sController)
            ->setActionName($sAction)
            ->setModuleName($sModule)
            ->setParams(['sSearch' => $sSearch, 'sCategory' => $sCategory]);

        // Create mocked Subject to be passed in mocked args
        $subject = $this->getMockBuilder('\Enlight_Controller_Action')
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder('\Enlight_Controller_ActionEventArgs')
            ->setMethods(['getSubject', 'getRequest'])
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

        $sessionArray = [
            ['isSearchPage', isset($sSearch)],
            ['isCategoryPage', isset($sCategory)]
        ];

        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        $isCategoryPage = Shopware()->Session()->offsetGet('isCategoryPage');
        $isSearchPage = Shopware()->Session()->offsetGet('isSearchPage');

        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $frontend = new \FinSearchUnified\Subscriber\Frontend($plugin->getPath(), new Enlight_Template_Manager());

        $frontend->onFrontendPreDispatch($args);

        $this->assertEquals(
            $isCategoryPage,
            Shopware()->Session()->offsetGet('isCategoryPage'),
            "Expected isCategoryPage to remain unchanged after listingCount method call"
        );
        $this->assertEquals(
            $isSearchPage,
            Shopware()->Session()->offsetGet('isSearchPage'),
            "Expected isSearchPage to remain unchanged after listingCount method call"
        );
    }
}
