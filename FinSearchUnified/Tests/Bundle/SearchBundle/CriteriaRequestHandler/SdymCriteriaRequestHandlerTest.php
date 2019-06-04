<?php

use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler\SdymCriteriaRequestHandler;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\Helper;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestContext;

class SdymCriteriaRequestHandlerTest extends TestCase
{
    /** @var RequestHttp */
    public $request;
    /** @var Criteria */
    public $criteria;
    /** @var TestContext */
    public $context;
    /** @var SdymCriteriaRequestHandler */
    public $handler;

    protected function setUp()
    {
        parent::setUp();


        $this->criteria = new Criteria();
        $this->context = $this->getContext();
        $this->handler = new SdymCriteriaRequestHandler();
    }

    public function getContext()
    {
        $helper = new Helper();
        $tax = $helper->createTax();
        $customerGroup = $helper->createCustomerGroup();
        $shop = $helper->getShop(1);

        return $helper->createContext(
            $customerGroup,
            $shop,
            [$tax]
        );
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
            ],
            'ParamKey is something different' => [
                true,
                false,
                'hey'
            ]
        ];
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($paramValue, $shouldExist, $paramKey = Constants::SDYM_PARAM_FORCE_QUERY)
    {
        $request = new RequestHttp();
        $request->setParams([$paramKey => $paramValue]);

        $this->handler->handleRequest($request, $this->criteria, $this->context);

        if ($shouldExist) {
            $this->assertEquals(Constants::SDYM_PARAM_FORCE_QUERY, $this->criteria->getConditions()[0]->getName());
            $this->assertEquals(SimpleCondition::class, get_class($this->criteria->getConditions()[0]));
        } else {
            $this->assertEmpty($this->criteria->getConditions());
            $this->assertEquals('array', gettype($this->criteria->getConditions()));
        }
    }
}
