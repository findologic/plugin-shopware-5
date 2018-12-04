<?php

namespace FinSearchUnified\Tests\Helper;

use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Plugin\Plugin;

class UrlBuilderTest extends TestCase
{
    /**
     * @var UrlBuilder A mock of the used url builder.
     */
    private $urlBuilder;

    /**
     * @var \Zend_Http_Client A mock of the used http client.
     */
    private $httpClient;

    /**
     * @var \Zend_Http_Response
     */
    private $httpResponse;

    const SHOPKEY = 'ABCD12345';

    public function setUp()
    {
        $this->httpResponse = new \Zend_Http_Response(200, []);
        $httpClientMock = $this->getMockBuilder(\Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $urlBuilderMock = $this->getMockBuilder(UrlBuilder::class)
            ->setMethods(['isAlive', 'getShopkey'])
            ->getMock();
        $urlBuilderMock->expects($this->atLeastOnce())
            ->method('isAlive')
            ->willReturn(true)
            ->method('getShopkey')
            ->willReturn(self::SHOPKEY);

        $this->httpClient = $httpClientMock;
        $this->urlBuilder = new $urlBuilderMock($this->httpClient);
        $this->setClientIp($_SERVER['HTTP_CLIENT_IP']);
    }

    public function testMockedHttpClientReturnsMockedResponseObject()
    {
        $response = $this->httpClient->request();
        $this->assertEquals($this->httpResponse, $response);
    }

    public function testBuildQueryUrlAndGetResponseReturnsMockedResponse()
    {
        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $response = $this->urlBuilder->buildQueryUrlAndGetResponse($criteria);

        $this->assertEquals($this->httpResponse, $response);
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
            'Different Ips separated by comma and space' => ['192.168.0.1, 10.10.0.200, ', '192.168.0.1,10.10.0.200'],
            'IP Unknown' => ['UNKNOWN', 'UNKNOWN']
        ];
    }

    /**
     * @dataProvider ipAddressProvider
     *
     * @param string $unfilteredIp
     * @param string $filteredIp
     */
    public function testIpFilterWorksAsExpected($unfilteredIp, $filteredIp)
    {
        $this->setClientIp($unfilteredIp);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $response = $this->urlBuilder->buildQueryUrlAndGetResponse($criteria);
        /** @var \Zend_Uri_Http $requestedUrl */
        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals($usedIpInRequest, $filteredIp);
    }

    /**
     * @param string $ipAddress The ip address to set.
     */
    private function setClientIp($ipAddress)
    {
        $_SERVER['HTTP_CLIENT_IP'] = $ipAddress;
    }
}
