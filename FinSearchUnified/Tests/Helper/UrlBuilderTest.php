<?php

namespace FinSearchUnified\Tests\Helper;

use Exception;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\UrlBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware_Components_Config;
use Zend_Http_Client;
use Zend_Http_Client_Exception;
use Zend_Http_Exception;
use Zend_Http_Response;

class UrlBuilderTest extends TestCase
{
    /**
     * @var Zend_Http_Client A mock of the used http client.
     */
    private $httpClient;

    protected function setUp():void
    {
        parent::setUp();

        $this->httpClient = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
    }

    protected function tearDown():void
    {
        parent::tearDown();

        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    /**
     * @return array
     */
    public function successfulRequestProvider()
    {
        return [
            'Request was successful with DI enabled' => ['{"directIntegration":{"enabled":1}}', true],
            'Request was successful with DI disabled' => ['{"directIntegration":{"enabled":0}}', false]
        ];
    }

    public function unsuccessfulRequestProvider()
    {
        return [
            'Request was not successful with DI disabled' => ['API', false],
            'Request was not successful with DI enabled' => ['Direct Integration', true]
        ];
    }

    public function exceptionOnRequestProvider()
    {
        return [
            'Request causes "Zend_Http_Client_Exception" with DI enabled' => ['Direct Integration', true],
            'Request causes "Zend_Http_Client_Exception" with DI disabled' => ['API', false]
        ];
    }

    /**
     * @dataProvider successfulRequestProvider
     *
     * @param string $responseBody
     * @param bool $expectedStatus
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testIntegrationTypeOnSuccessfulRequest($responseBody, $expectedStatus)
    {
        $configArray = [
            ['IntegrationType', Constants::INTEGRATION_TYPE_DI]
        ];

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $httpResponse = new Zend_Http_Response(200, [], $responseBody);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($httpResponse);

        $urlBuilder = new UrlBuilder($this->httpClient);

        $status = $urlBuilder->getConfigStatus();

        $this->assertEquals(
            $expectedStatus,
            $status,
            sprintf(
                'Expected integration type to be "%s"',
                $expectedStatus ? 'Direct Integration' : 'API'
            )
        );
    }

    /**
     * @dataProvider unsuccessfulRequestProvider
     *
     * @param string $integrationType
     * @param bool $expectedStatus
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testIntegrationTypeOnUnsuccessfulRequest($integrationType, $expectedStatus)
    {
        $configArray = [
            ['IntegrationType', $integrationType]
        ];

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $httpResponse = new Zend_Http_Response(500, [], '');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($httpResponse);

        $urlBuilder = new UrlBuilder($this->httpClient);

        $status = $urlBuilder->getConfigStatus();

        $this->assertEquals(
            $expectedStatus,
            $status,
            sprintf(
                'Expected integration type to be "%s"',
                $integrationType
            )
        );
    }

    /**
     * @dataProvider exceptionOnRequestProvider
     *
     * @param string $integrationType
     * @param bool $expectedStatus
     *
     * @throws Exception
     */
    public function testIntegrationTypeOnException($integrationType, $expectedStatus)
    {
        $configArray = [
            ['IntegrationType', $integrationType]
        ];

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Zend_Http_Client_Exception());

        $urlBuilder = new UrlBuilder($this->httpClient);

        $status = $urlBuilder->getConfigStatus();

        $this->assertEquals(
            $expectedStatus,
            $status,
            sprintf(
                'Expected integration type to be "%s"',
                $integrationType
            )
        );
    }
}
