<?php

namespace FinSearchUnified\Tests\Helper;

use Exception;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
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

    /**
     * @throws \Zend_Http_Exception
     */
    public function setUp()
    {
        parent::setUp();

        $this->httpResponse = new Zend_Http_Response(200, [], 'alive');

        $httpClientMock = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
        $httpClientMock->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($this->httpResponse);

        $this->httpClient = $httpClientMock;
    }

    protected function tearDown()
    {
        parent::tearDown();

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            unset($_SERVER['HTTP_CLIENT_IP']);
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
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
     * @throws Exception
     */
    public function testSendOnlyUniqueUserIps($unfilteredIp, $expectedValue)
    {
        $this->setIpHeader('HTTP_CLIENT_IP', $unfilteredIp);

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
     * @throws Exception
     */
    public function testSendsOnlyClientIpFromReverseProxy($unfilteredIp)
    {
        $this->setIpHeader('HTTP_X_FORWARDED_FOR', $unfilteredIp);

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);
        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $usedIpInRequest = $requestedUrl['userip'];

        $this->assertEquals('192.168.0.1', $usedIpInRequest);
    }

    /**
     * @throws Exception
     */
    public function testHandlesUnknownClientIp()
    {
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
     * @throws Exception
     */
    public function testOutputAdapterIsExplicitlySetToXml()
    {
        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);
        /** @var Zend_Uri_Http $requestedUrl */
        $requestedUrl = $this->httpClient->getUri();
        $path = $requestedUrl->getPath();
        $this->assertNotContains(
            'xml_2.0',
            $path,
            'Expected "xml_2.0" to not be passed in requested URL'
        );

        $queryArray = $requestedUrl->getQueryAsArray();

        $this->assertArrayHasKey(
            'outputAdapter',
            $queryArray,
            '"outputAdapter" was not found in query parameters'
        );
        $this->assertSame(
            'XML_2.0',
            $queryArray['outputAdapter'],
            '"XML_2.0" was not found in "outputAdapter" parameter'
        );
    }

    /**
     * @param string $field
     * @param string $ipAddress The ip address to set.
     */
    private function setIpHeader($field, $ipAddress)
    {
        $_SERVER[$field] = $ipAddress;
    }
}
