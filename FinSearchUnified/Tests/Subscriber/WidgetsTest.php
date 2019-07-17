<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use Enlight_Hook_HookArgs;
use ReflectionException;
use FinSearchUnified\Tests\TestCase;
use Shopware_Controllers_Widgets_Listing;

class WidgetsTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Shopware()->Container()->get('config_writer')->save('ActivateFindologic', true);
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Session()->offsetUnset('isSearchPage');
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

        /** @var Shopware_Controllers_Widgets_Listing $subject */
        $subject = Shopware_Controllers_Widgets_Listing::Instance(
            Shopware_Controllers_Widgets_Listing::class,
            [
                $request,
                new Enlight_Controller_Response_ResponseHttp()
            ]
        );

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
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets');

        /** @var Shopware_Controllers_Widgets_Listing $subject */
        $subject = Shopware_Controllers_Widgets_Listing::Instance(
            Shopware_Controllers_Widgets_Listing::class,
            [
                $request,
                new Enlight_Controller_Response_ResponseHttp()
            ]
        );

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
}
