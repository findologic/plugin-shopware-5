<?php

namespace findologicDI\Tests;

use findologicDI\findologicDI as Plugin;
use Shopware\Components\Test\Plugin\TestCase;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'findologicDI' => []
    ];

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['findologicDI'];

        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
