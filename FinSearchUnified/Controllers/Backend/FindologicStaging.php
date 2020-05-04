<?php

use FinSearchUnified\Components\ConfigLoader;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_FindologicStaging extends Shopware_Controllers_Backend_ExtJs implements
    CSRFWhitelistAware
{
    public function indexAction()
    {
        try {
            $cacheManager = Shopware()->Container()->get('shopware.cache_manager');
            $cacheManager->clearConfigCache();

            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender(true);
            $shopkey = Shopware()->Config()->offsetGet('ShopKey');
            $isActive = Shopware()->Config()->offsetGet('ActivateFindologic');

            if (!$isActive) {
                throw new RuntimeException('Please ensure the plugin is active!');
            }
            if (!preg_match('/^[A-F0-9]{32}$/', $shopkey)) {
                throw new RuntimeException('Please ensure a valid ShopKey is provided!');
            }
            $shop = null;
            $queryParam = '';
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

            /** @var ConfigLoader $configLoader */
            $configLoader = Shopware()->Container()->get('fin_search_unified.config_loader');
            try {
                $isStaging = $configLoader->isStagingShop();
            } catch (\Exception $e) {
                $isStaging = false;
            }
            if ($isStaging) {
                $queryParam = '?findologic=on';
            }
            $this->redirect(
                sprintf(
                    '%s://%s%s',
                    $shop->getSecure() ? 'https' : 'http',
                    rtrim($shop->getHost(), '/'),
                    $queryParam
                )
            );
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }
}
