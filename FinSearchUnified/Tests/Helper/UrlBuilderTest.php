<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Components_Session_Namespace;
use Exception;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Sorting\ProductNameSorting;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware_Components_Config;
use Zend_Http_Client;
use Zend_Http_Client_Exception;
use Zend_Http_Exception;
use Zend_Http_Response;
use Zend_Uri_Http;

class UrlBuilderTest extends TestCase
{
    /**
     * @var Zend_Http_Client A mock of the used http client.
     */
    private $httpClient;

    protected function setUp()
    {
        parent::setUp();

        $this->httpClient = $this->getMockBuilder(Zend_Http_Client::class)
            ->setMethods(['request'])
            ->getMock();
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');

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
     * @throws Exception
     */
    public function testSendOnlyUniqueUserIps($unfilteredIp, $expectedValue)
    {
        $this->setIpHeader('HTTP_CLIENT_IP', $unfilteredIp);

        $httpResponse = new Zend_Http_Response(200, [], 'alive');

        $this->httpClient->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($httpResponse);

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

        $httpResponse = new Zend_Http_Response(200, [], 'alive');

        $this->httpClient->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($httpResponse);

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
        $httpResponse = new Zend_Http_Response(200, [], 'alive');

        $this->httpClient->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($httpResponse);

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
        $httpResponse = new Zend_Http_Response(200, [], 'alive');

        $this->httpClient->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($httpResponse);

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

    /**
     * @return array
     */
    public function forceOriginalQueryProvider()
    {
        return [
            'forceOriginalQuery not present' => [null],
            'forceOriginalQuery present and truthy' => [1],
            'forceOriginalQuery present and falsy' => [0]
        ];
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
     * @dataProvider forceOriginalQueryProvider
     *
     * @param int|null $forceOriginalQuery
     *
     * @throws \Exception
     */
    public function testBuildUrlAndResponse($forceOriginalQuery)
    {
        $sessionArray = [
            ['isSearchPage', true]
        ];
        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder(Enlight_Components_Session_Namespace::class)
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        $_GET['forceOriginalQuery'] = $forceOriginalQuery;

        $httpResponse = new Zend_Http_Response(200, [], 'alive');

        $this->httpClient->expects($this->atLeastOnce())
            ->method('request')
            ->willReturn($httpResponse);

        $urlBuilder = new UrlBuilder($this->httpClient);

        // Create criteria object with necessary conditions
        $criteria = new Criteria();
        $criteria->offset(0)->limit(2)
            ->addBaseCondition(new SearchTermCondition('findologic'))
            ->addBaseCondition(new CategoryCondition([Shopware()->Shop()->getCategory()->getId()]))
            ->addSorting(new ProductNameSorting());

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);

        $requestedUrl = $this->httpClient->getUri()->getQueryAsArray();
        $url = $this->httpClient->getUri();
        $actualUrl = sprintf('%s://%s%s', $url->getScheme(), $url->getHost(), $url->getPath());

        $this->assertSame(
            sprintf('https://service.findologic.com/ps/%s/index.php', Shopware()->Shop()->getHost()),
            $actualUrl,
            'The resulting URL is not correct'
        );
        $this->assertArrayHasKey(
            'userip',
            $requestedUrl,
            '"userip" was not found in the query parameters'
        );
        $this->assertArrayHasKey(
            'revision',
            $requestedUrl,
            '"revision" was not found in the query parameters'
        );
        $this->assertArrayHasKey(
            'shopkey',
            $requestedUrl,
            '"shopkey" was not found in the query parameters'
        );
        if ($forceOriginalQuery === null) {
            $this->assertArrayNotHasKey(
                'forceOriginalQuery',
                $requestedUrl,
                'Expected "forceOriginalQuery" parameter to NOT be present'
            );
        } else {
            $this->assertArrayHasKey(
                'forceOriginalQuery',
                $requestedUrl,
                'Expected "forceOriginalQuery" parameter to be present'
            );
        }
        $this->assertEquals(
            $forceOriginalQuery,
            $requestedUrl['forceOriginalQuery'],
            'forceOriginalQuery was not processed correctly'
        );
        $this->assertTrue($criteria->hasBaseCondition('search'));
        $this->assertTrue($criteria->hasBaseCondition('category'));
        $this->assertTrue($criteria->hasSorting('product_name'));
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
