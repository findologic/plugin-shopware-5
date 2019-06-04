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
    /**
     * @var RequestHttp
     */
    public $request;

    /**
     * @var Criteria
     */
    public $criteria;

    /**
     * @var TestContext
     */
    public $context;

    /**
     * @var SdymCriteriaRequestHandler
     */
    public $handler;

    protected function setUp()
    {
        parent::setUp();

        $this->criteria = new Criteria();
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
        $this->handler = new SdymCriteriaRequestHandler();
    }

    public function handleDataProvider()
    {
        return [
            'Value is true' => [
                true,
                true,
                Constants::SDYM_PARAM_FORCE_QUERY
            ],
            'Value is false' => [
                false,
                false,
                Constants::SDYM_PARAM_FORCE_QUERY
            ],
            'Value is null' => [
                null,
                false,
                Constants::SDYM_PARAM_FORCE_QUERY
            ],
            'Value is empty string' => [
                '',
                false,
                Constants::SDYM_PARAM_FORCE_QUERY
            ],
            'Value is non empty string' => [
                'hey there boi',
                true,
                Constants::SDYM_PARAM_FORCE_QUERY
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
    public function testHandle($paramValue, $shouldExist, $paramKey)
    {
        $request = new RequestHttp();
        $request->setParams([$paramKey => $paramValue]);

        $this->handler->handleRequest($request, $this->criteria, $this->context);

        if ($shouldExist) {
            $this->assertTrue($this->criteria->hasCondition(Constants::SDYM_PARAM_FORCE_QUERY));
        } else {
            $this->assertEmpty($this->criteria->getConditions());
        }
    }
}
