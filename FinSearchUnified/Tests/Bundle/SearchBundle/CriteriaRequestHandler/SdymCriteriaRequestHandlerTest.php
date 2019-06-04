<?php

use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ProductService as RandomContext;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler\SdymCriteriaRequestHandler;

class SdymCriteriaRequestHandlerTest extends TestCase
{
    /** @var RequestHttp */
    public $request;
    /** @var Criteria */
    public $criteria;
    /** @var RandomContext */
    public $context;
    /** @var SdymCriteriaRequestHandler */
    public $handler;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->request = $this->getMockBuilder(RequestHttp::class)->setMethods(['getParam'])->getMock();
        $this->criteria = new Criteria();
        $this->context = new RandomContext();
        $this->handler = new SdymCriteriaRequestHandler();
    }

    public function handleDataProvider()
    {
        return [
            'Value is true' => [
                true,
                true
            ],
            'Value is false' => [
                false,
                false
            ],
            'Value is null' => [
                null,
                false
            ],
            'Value is empty string' => [
                '',
                false
            ],
            'Value is non empty string' => [
                'hey there boi',
                true
            ]
        ];
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($param, $shouldExist)
    {
        $this->request->expects($this->once())
            ->method('getParam')
            ->willReturn($param)
            ->with(Constants::SDYM_PARAM_FORCE_QUERY);

        $this->handler->handleRequest($this->request, $this->criteria, $this->context);

        if ($shouldExist) {
            $this->assertEquals(Constants::SDYM_PARAM_FORCE_QUERY, $this->criteria->getConditions()[0]->getName());
            $this->assertEquals(SimpleCondition::class, get_class($this->criteria->getConditions()[0]));
        } else {
            $this->assertEmpty($this->criteria->getConditions());
            $this->assertEquals('array', gettype($this->criteria->getConditions()));
        }
    }
}