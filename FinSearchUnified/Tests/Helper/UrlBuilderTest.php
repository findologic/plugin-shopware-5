<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Components_Session_Namespace;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Sorting\ProductNameSorting;
use Shopware\Components\Test\Plugin\TestCase;
use Zend_Http_Client;
use Zend_Http_Exception;
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
     * @throws Zend_Http_Exception
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

        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
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
     *
     * @throws \Exception
     */
    public function testSendOnlyUniqueUserIps($unfilteredIp, $expectedValue)
    {
        $this->setClientIp($unfilteredIp);

        $urlBuilder = new UrlBuilder($this->httpClient);

        $criteria = new Criteria();
        $criteria->offset(0)->limit(2);

        $urlBuilder->buildQueryUrlAndGetResponse($criteria);
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

    /**
     * @return array
     */
    public function forceOriginalQueryProvider()
    {
        return [
            'forceOriginalQuery not present' => [null],
            'forceOriginalQuery present and truthy' => [1],
            'forceOriginalQuery present and falsy' => [0],
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
            sprintf('https://service.findologic.com/ps/xml_2.0/%s/index.php', Shopware()->Shop()->getHost()),
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
}
