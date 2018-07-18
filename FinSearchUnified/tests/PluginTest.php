<?php

namespace FinSearchUnified\tests;

use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\ShopwareProcess;
use Shopware\Components\Test\Plugin\TestCase;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [],
    ];

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];

        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testCalculateGroupkey()
    {
        $shopkey = 'ABCD0815';
        $usergroup = 'at_rated';

        $hash = ShopwareProcess::calculateUsergroupHash($shopkey, $usergroup);
        $decrypted = ShopwareProcess::decryptUsergroupHash($shopkey, $hash);

        $this->assertEquals($usergroup, $decrypted);
    }
}
