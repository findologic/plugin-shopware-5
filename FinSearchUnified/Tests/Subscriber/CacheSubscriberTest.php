<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use FinSearchUnified\Subscriber\CacheSubscriber;
use Shopware\Components\CacheManager;

class CacheSubscriberTest extends SubscriberTestCase
{
    public function testCache()
    {

        $mockCache = $this->createMock(CacheManager::class);
        $mockCache->expects($this->once())
            ->method('clearByTag')
            ->with('config');

        $pluginName = 'FinSearchUnified';
        $cache = new CacheSubscriber($pluginName, $mockCache);
        $request = new Enlight_Controller_Request_RequestHttp();

        $request->setParam('name', $pluginName);
        $request->server->set('REQUEST_METHOD', 'POST');

        $subject = $this->getMockBuilder(Enlight_Controller_Action::class)
            ->disableOriginalConstructor()
            ->getMock();
        $subject->method('Request')
            ->willReturn($request);

        $args = $this->createMock(Enlight_Event_EventArgs::class);
        $args->method('get')->with('subject')->willReturn($subject);

        $cache->onPostDispatchConfig($args);
    }

    public function testNotClearCache()
    {

        $mockCache = $this->createMock(CacheManager::class);
        $mockCache->expects($this->never())
            ->method('clearByTag')
            ->with('config');

        $pluginName = 'FinSearchUnified';
        $cache = new CacheSubscriber($pluginName, $mockCache);
        $request = new Enlight_Controller_Request_RequestHttp();

        $request->setParam('name', $pluginName);
        $request->server->set('REQUEST_METHOD', 'GET');

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
