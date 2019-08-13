<?php

namespace FinSearchUnified\Components;

use Enlight_Controller_Request_Request as Request;
use Zend_Cache_Exception;

/**
 * Manages FINDOLOGIC Staging.
 */
class StagingManager
{
    /**
     * Sets the current staging flag based on the request params. Query params:
     * * `findologic=on` => sets staging flag to `true`.
     * * `findologic=off`/`findologic=disabled` => sets staging flag to `false`.
     *
     * @param Request $request
     */
    public function setStagingFlagByRequest(Request $request)
    {
        $stagingFlag = $request->getParam('findologic');

        if ($stagingFlag === 'on') {
            Shopware()->Session()->offsetSet('stagingFlag', true);
        } elseif ($stagingFlag === 'off' || $stagingFlag === 'disabled') {
            Shopware()->Session()->offsetSet('stagingFlag', false);
        }
    }

    /**
     * Returns false if the shop is no staging shop or the staging flag is enabled, to ignore this check.
     * Otherwise true is returned.
     *
     * @return bool
     * @throws Zend_Cache_Exception
     */
    public function isStaging()
    {
        $isStagingShop = $this->getStagingFlagFromShopConfig();
        $stagingFlag = Shopware()->Session()->offsetGet('stagingFlag');

        if (!$isStagingShop || $stagingFlag) {
            return false;
        }

        return true;
    }

    /**
     * @return bool|null
     * @throws Zend_Cache_Exception
     */
    private function getStagingFlagFromShopConfig()
    {
        /** @var ConfigLoader $configLoader */
        $configLoader = Shopware()->Container()->get('fin_search_unified.config_loader');
        return $configLoader->isStagingShop();
    }
}
