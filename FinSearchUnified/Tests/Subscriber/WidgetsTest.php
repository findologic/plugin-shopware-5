<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use Enlight_Hook_HookArgs;
use ReflectionException;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware_Controllers_Widgets_Listing;

class WidgetsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // reset global variable as it conflicts with existing tests in this class
        $_GET = [];
    }

    /**
     * @dataProvider searchParameterProvider
     *
     * @param array $requestParameters
     * @param string|null $expectedSearchParameter
     * @param string $expectedMessage
     *
     * @throws ReflectionException
     */
    public function testBeforeListingCountAction(
        array $requestParameters,
        $expectedSearchParameter,
        $expectedMessage
    ) {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets')
            ->setParams($requestParameters);

        /** @var Shopware_Controllers_Widgets_Listing $subject */
        $subject = Shopware_Controllers_Widgets_Listing::Instance(
            Shopware_Controllers_Widgets_Listing::class,
            [
                $request,
                new Enlight_Controller_Response_ResponseHttp()
            ]
        );

        // Create mocked args for getting Subject and Request
        $args = $this->getMockBuilder(Enlight_Hook_HookArgs::class)
            ->setMethods(['getSubject', 'getRequest'])
            ->disableOriginalConstructor()
            ->getMock();
        $args->method('getSubject')->willReturn($subject);
        $args->method('getRequest')->willReturn($request);

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->beforeListingCountAction($args);

        $params = $subject->Request()->getParams();

        foreach ($requestParameters as $key => $value) {
            $this->assertArrayHasKey($key, $params, sprintf('Expected %s to be present', $key));
            $this->assertSame($value, $params[$key], sprintf('Expected %s to have correct values', $key));
        }

        if ($expectedSearchParameter === null) {
            $this->assertArrayNotHasKey('sSearch', $params, 'Expected no query parameter to be set');
        } else {
            $this->assertEquals($expectedSearchParameter, $params['sSearch'], $expectedMessage);
        }
    }

    public function searchParameterProvider()
    {
        return [
            'Only category ID 5 was requested' => [
                ['sCategory' => 5],
                null,
                'Expected only "sCategory" to be present'
            ],
            'Only search term "blubbergurke" was requested' => [
                ['sSearch' => 'blubbergurke'],
                'blubbergurke',
                'Expected only "sSearch" parameter to be present'
            ],
            'Neither search nor category listing was requested' => [
                [],
                ' ',
                'Expected "sSearch" to be present with single whitespace character'
            ],
        ];
    }
}
