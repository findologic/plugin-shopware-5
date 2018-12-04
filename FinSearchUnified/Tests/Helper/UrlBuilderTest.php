<?php

namespace FinSearchUnified\Tests\Helper;

use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
use \Zend_Http_Client;
use \Zend_Http_Response;
use \Zend_Uri_Http;

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

    public function setUp()
    {
        $this->httpResponse = new Zend_Http_Response(200, [], 'alive');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;
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
            'Different IPs separated by comma and space' => ['192.168.0.1, 10.10.0.200', '192.168.0.1,10.10.0.200'],
            'IP Unknown' => ['UNKNOWN', 'UNKNOWN']
        ];
    }

    /**
     * @dataProvider ipAddressProvider
     *
     * @param string $unfilteredIp
     * @param string $expectedValue
     */
    public function testIpFilterWorksAsExpected($unfilteredIp, $expectedValue)
    {
        $this->setClientIp($unfilteredIp);

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $response = $urlBuilder->buildQueryUrlAndGetResponse($criteria);
        /** @var Zend_Uri_Http $requestedUrl */
        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals($expectedValue, $usedIpInRequest);
    }

    /**
     * @param string $ipAddress The ip address to set.
     */
    private function setClientIp($ipAddress)
    {
        $_SERVER['HTTP_CLIENT_IP'] = $ipAddress;
    }
}
