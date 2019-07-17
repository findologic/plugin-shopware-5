<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\NavigationQueryBuilder;
use PHPUnit\Framework\Assert;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware\Components\HttpClient\Response;
use FinSearchUnified\Tests\TestCase;
use Shopware_Components_Config;

class NavigationQueryBuilderTest extends TestCase
{
    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var InstallerService
     */
    private $installerService;

    protected function setUp():void
    {
        parent::setUp();

        $this->config = Shopware()->Container()->get('config');
        $this->installerService = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        Shopware()->Session()->offsetSet('isSearchPage', false);
    }

    protected function tearDown():void
    {
        parent::tearDown();

        Shopware()->Session()->offsetUnset('isSearchPage');
    }

    /**
     * @throws Exception
     */
    public function testEndpointUrl()
    {
        $plugin = $this->installerService->getPluginByName('FinSearchUnified');
        $shopUrl = rtrim(Shopware()->Shop()->getHost(), '/');
        $parameters = [
            'outputAdapter' => 'XML_2.0',
            'userip' => 'UNKNOWN',
            'revision' => $plugin->getVersion(),
            'shopkey' => $this->config->offsetGet('ShopKey')
        ];
        $aliveUrl = sprintf(
            'https://service.findologic.com/ps/%s/alivetest.php?shopkey=%s',
            $shopUrl,
            $parameters['shopkey']
        );
        $executeUrl = sprintf(
            'https://service.findologic.com/ps/%s/selector.php?%s',
            $shopUrl,
            http_build_query($parameters)
        );
        $httpResponse = new Response(200, [], 'alive');
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);
        $aliveCallback = $this->returnCallback(function ($url) use ($aliveUrl, $httpResponse) {
            Assert::assertSame($aliveUrl, $url);

            return $httpResponse;
        });
        $executeCallback = $this->returnCallback(function ($url) use ($executeUrl, $httpResponse) {
            Assert::assertSame($executeUrl, $url);

            return $httpResponse;
        });
        $httpClientMock->expects($this->exactly(2))
            ->method('get')
            ->will($this->onConsecutiveCalls($aliveCallback, $executeCallback));

        $querybuilder = new NavigationQueryBuilder(
            $httpClientMock,
            $this->installerService,
            $this->config
        );
        $querybuilder->execute();
    }

    /**
     * @throws Exception
     */
    public function testAddCategoriesMethod()
    {
        $categories = ['Genusswelten', 'Sommerwelten'];

        $querybuilder = new NavigationQueryBuilder(
            Shopware()->Container()->get('http_client'),
            $this->installerService,
            $this->config
        );

        $querybuilder->addCategories($categories);

        $parameters = $querybuilder->getParameters();
        $this->assertArrayHasKey('selected', $parameters);
        $this->assertArrayHasKey('cat', $parameters['selected']);

        $this->assertEquals(
            $categories,
            $parameters['selected']['cat'],
            'Expected both categories to be available in parameters'
        );
    }
}
