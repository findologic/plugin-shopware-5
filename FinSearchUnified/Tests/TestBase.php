<?php

namespace FinSearchUnified\Tests;

use Shopware\Components\Test\Plugin\TestCase;

class TestBase extends TestCase
{
    /**
     * Allows to set a Shopware config
     *
     * @param string $name
     * @param mixed $value
     */
    protected static function setConfig($name, $value)
    {
        Shopware()->Container()->get('config_writer')->save($name, $value);
        Shopware()->Container()->get('cache')->clean();
        Shopware()->Container()->get('config')->setShop(Shopware()->Shop());
    }
}
