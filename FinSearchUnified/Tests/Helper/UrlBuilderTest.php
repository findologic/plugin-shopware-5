<?php

namespace FinSearchUnified\Tests\Helper;

use FinSearchUnified\Constants;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware_Components_Config;
use Zend_Http_Client;
use Zend_Http_Response;
use Zend_Uri_Http;

class UrlBuilderTest extends TestCase
{
    /**
     * @var Zend_Http_Client A mock of the used http client.
     */
    private $httpClient;

    /**
     * @var Zend_Http_Response
     */
    private $httpResponse;

    protected function tearDown()
    {
        parent::tearDown();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            unset($_SERVER['HTTP_CLIENT_IP']);
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        }

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    /**
     * Scenarios of IPs which should be filtered
     *
     * @return array Cases with the value to be filtered and the expected return value.
     */
    public function ipAddressProvider()
    {
        return [
            'Single IP' => ['192.168.0.1', '192.168.0.1'],
            'Same IP twice separated by comma' => ['192.168.0.1,192.168.0.1', '192.168.0.1'],
            'Same IP twice separated by comma and space' => ['192.168.0.1, 192.168.0.1', '192.168.0.1'],
            'Different IPs separated by comma' => ['192.168.0.1,10.10.0.200', '192.168.0.1,10.10.0.200'],
            'Different IPs separated by comma and space' => ['192.168.0.1, 10.10.0.200', '192.168.0.1,10.10.0.200']
        ];
    }

    /**
     * Scenarios of proxy IPs which should be filtered
     *
     * @return array
     */
    public function reverseProxyIpAddressProvider()
    {
        return [
            'Single IP' => ['192.168.0.1'],
            'Same IP twice separated by comma' => ['192.168.0.1,192.168.0.1'],
            'Same IP twice separated by comma and space' => ['192.168.0.1, 192.168.0.1'],
            'Different IPs separated by comma' => ['192.168.0.1,10.10.0.200'],
            'Different IPs separated by comma and space' => ['192.168.0.1, 10.10.0.200']
        ];
    }

    /**
     * @dataProvider ipAddressProvider
     *
     * @param string $unfilteredIp
     * @param string $expectedValue
     *
     * @throws \Exception
     */
    public function testSendOnlyUniqueUserIps($unfilteredIp, $expectedValue)
    {
        $this->setIpHeader('HTTP_CLIENT_IP', $unfilteredIp);

        $this->httpResponse = new Zend_Http_Response(200, [], 'alive');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);

        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals($expectedValue, $usedIpInRequest);
    }

    /**
     * @dataProvider reverseProxyIpAddressProvider
     *
     * @param string $unfilteredIp
     *
     * @throws \Exception
     */
    public function testSendsOnlyClientIpFromReverseProxy($unfilteredIp)
    {
        $this->setIpHeader('HTTP_X_FORWARDED_FOR', $unfilteredIp);

        $this->httpResponse = new Zend_Http_Response(200, [], 'alive');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);
        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals('192.168.0.1', $usedIpInRequest);
    }

    /**
     * @throws \Exception
     */
    public function testHandlesUnknownClientIp()
    {
        $this->httpResponse = new Zend_Http_Response(200, [], 'alive');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);
        /** @var Zend_Uri_Http $requestedUrl */
        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals('UNKNOWN', $usedIpInRequest);
    }

    /**
     * @param string $field
     * @param string $ipAddress The ip address to set.
     */
    private function setIpHeader($field, $ipAddress)
    {
        $_SERVER[$field] = $ipAddress;
    }

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

    /**
     * @dataProvider successfulRequestProvider
     *
     * @param string $responseBody
     * @param bool $expectedStatus
     *
     * @throws \Zend_Http_Exception
     * @throws \Exception
     */
    public function testConfigStatusOnSuccessfulRequest($responseBody, $expectedStatus)
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

        $this->httpResponse = new Zend_Http_Response(200, [], $responseBody);

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;

        $urlBuilder = new UrlBuilder($this->httpClient);

        $status = $urlBuilder->getConfigStatus();

        $this->assertEquals(
            $expectedStatus,
            $status,
            sprintf(
                'Expected config status to return %s ',
                $expectedStatus ? 'true' : 'false'
            )
        );
    }

    /**
     * @dataProvider unsuccessfulRequestProvider
     *
     * @param string $integrationType
     * @param bool $expectedStatus
     *
     * @throws \Zend_Http_Exception
     */
    public function testConfigStatusOnUnsuccessfulRequest($integrationType, $expectedStatus)
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

        $this->httpResponse = new Zend_Http_Response(500, [], '');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;

        $urlBuilder = new UrlBuilder($this->httpClient);

        $status = $urlBuilder->getConfigStatus();

        $this->assertEquals(
            $expectedStatus,
            $status,
            sprintf(
                'Expected config status to return %s ',
                $expectedStatus ? 'true' : 'false'
            )
        );
    }
}
