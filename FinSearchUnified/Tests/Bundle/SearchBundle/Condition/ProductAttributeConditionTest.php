<?php

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Subscriber\Widgets;
use FinSearchUnified\Tests\TestCase;

class ProductAttributeConditionTest extends \FinSearchUnified\Tests\Subscriber\SubscriberTestCase
{
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

    public function exceptionDataProvider()
    {
        return [
            'The value for "field" is an integer' => [
                'field' => 1,
                'exceptionMessage' => 'Value "1" expected to be string, type integer given.'
            ],
            'The value for "field" is a string' => [
                'field' => 'vendor',
                'exceptionMessage' => null
            ],
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $field
     * @param string $exceptionMessage
     */
    public function testConditionException($field, $exceptionMessage)
    {
        try {
            $condition = new ProductAttributeCondition($field, Operator::EQ, 'Findologic');
            $this->assertEquals(sprintf('product_attribute_%s', $field), $condition->getName());
            $this->assertEquals('Findologic', $condition->getValue());
        } catch (\Assert\InvalidArgumentException $e) {
            $this->assertSame($exceptionMessage, $e->getMessage());
        } catch (Exception $e) {
            $this->fail();
        }
    }
}
