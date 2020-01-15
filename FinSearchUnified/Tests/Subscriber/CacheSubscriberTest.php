<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use FinSearchUnified\Subscriber\CacheSubscriber;
use Shopware\Components\CacheManager;

use function property_exists;

class CacheSubscriberTest extends SubscriberTestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testCacheIsCleared()
    {
        $mockCache = $this->createMock(CacheManager::class);
        $mockCache->expects($this->once())->method('clearConfigCache');

        $pluginName = 'FinSearchUnified';
        $cache = new CacheSubscriber($pluginName, $mockCache);
        $request = new Enlight_Controller_Request_RequestHttp();

        $request->setParam('name', $pluginName);

        // For Shopware 5.6 compatibility, otherwise fallback to previous versions
        if (property_exists($request, 'server')) {
            $request->server->set('REQUEST_METHOD', 'POST');
        } else {
            $_SERVER['REQUEST_METHOD'] = 'POST';
        }

        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        $args = $this->createMock(Enlight_Event_EventArgs::class);
        $args->method('get')->with('subject')->willReturn($subject);

        $cache->onPostDispatchConfig($args);
    }

    public function testCacheIsNotCleared()
    {
        $mockCache = $this->createMock(CacheManager::class);
        $mockCache->expects($this->never())->method('clearConfigCache');

        $pluginName = 'FinSearchUnified';
        $cache = new CacheSubscriber($pluginName, $mockCache);
        $request = new Enlight_Controller_Request_RequestHttp();

        $request->setParam('name', $pluginName);

        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        $args = $this->createMock(Enlight_Event_EventArgs::class);
        $args->method('get')->with('subject')->willReturn($subject);

        $cache->onPostDispatchConfig($args);
    }
}
