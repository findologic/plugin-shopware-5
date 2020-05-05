<?php

use FinSearchUnified\Components\ConfigLoader;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_FindologicStaging extends Shopware_Controllers_Backend_ExtJs implements
    CSRFWhitelistAware
{
    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }

    /**
     * @throws Exception
     */
    public function indexAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender(true);
        $this->assertPluginIsActive();

        $shopkey = Shopware()->Config()->offsetGet('ShopKey');
        $this->assertShopKeyIsValid($shopkey);
        $shop = $this->getCurrentShop($shopkey);
        $isStaging = $this->isStaging();

        $this->redirect($this->getRedirectUrl($isStaging, $shop));
    }

    private function assertPluginIsActive()
    {
        $isActive = Shopware()->Config()->offsetGet('ActivateFindologic');

        if (!$isActive) {
            throw new RuntimeException('Please ensure the plugin is active!');
        }
    }

    /**
     * @param string $shopkey
     */
    private function assertShopKeyIsValid($shopkey)
    {
        if (!preg_match('/^[A-F0-9]{32}$/', $shopkey)) {
            throw new RuntimeException('Please ensure a valid ShopKey is provided!');
        }
    }

    /**
     * @param string $shopkey
     *
     * @return Shop
     */
    private function getCurrentShop($shopkey)
    {
        $shop = null;
        $configValue = Shopware()->Models()->getRepository(Value::class)->findOneBy(['value' => $shopkey]);
        if ($configValue && $configValue->getShop()) {
            $shopId = $configValue->getShop()->getId();

            if (Shopware()->Container()->has('shop') && $shopId === Shopware()->Shop()->getId()) {
                $shop = Shopware()->Shop();
            } else {
                /** @var Repository $shopRepository */
                $shopRepository = Shopware()->Models()->getRepository(Shop::class);
                $shop = $shopRepository->getActiveById($shopId);
            }
        }
        if (!$shop) {
            throw new RuntimeException('Provided shopkey not assigned to any shop!');
        }

        return $shop;
    }

    /**
     * @return bool
     */
    private function isStaging()
    {
        /** @var ConfigLoader $configLoader */
        $configLoader = Shopware()->Container()->get('fin_search_unified.config_loader');
        try {
            $isStaging = $configLoader->isStagingShop();
        } catch (Exception $e) {
            $isStaging = false;
        }

        return $isStaging;
    }

    /**
     * @param bool $isStaging
     * @param Shop $shop
     *
     * @return string
     */
    private function getRedirectUrl($isStaging, $shop)
    {
        $queryParam = '';
        if ($isStaging) {
            $queryParam = '?findologic=on';
        }

        return sprintf(
            '%s://%s%s',
            $shop->getSecure() ? 'https' : 'http',
            rtrim($shop->getHost(), '/'),
            $queryParam
        );
    }
}
