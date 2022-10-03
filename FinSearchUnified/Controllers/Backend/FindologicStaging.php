<?php

use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Exceptions\StagingModeException;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\DetachedShop;
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

    public function indexAction()
    {
        try {
            $this->Front()->Plugins()->ViewRenderer()->setNoRender(true);
            $this->assertPluginIsActive();

            $shop = $this->getCurrentShop();
            $this->redirect($this->getRedirectUrl($shop));
        } catch (StagingModeException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @throws StagingModeException
     */
    private function assertPluginIsActive()
    {
        $isActive = Shopware()->Config()->getByNamespace('FinSearchUnified', 'ActivateFindologic');

        if (!$isActive) {
            throw new StagingModeException('Please ensure the plugin is active!');
        }
    }

    /**
     * @param string $shopkey
     *
     * @throws StagingModeException
     */
    private function assertShopKeyIsValid($shopkey)
    {
        if (!preg_match('/^[A-F0-9]{32}$/', $shopkey)) {
            throw new StagingModeException('Please ensure a valid Shopkey is provided!');
        }
    }

    /**
     * @return Shop
     * @throws StagingModeException
     */
    private function getCurrentShop()
    {
        $shopkey = Shopware()->Front()->Request()->get('shopkey');
        $this->assertShopKeyIsValid($shopkey);

        /** @var Value $configValue */
        $configValue = Shopware()->Models()->getRepository(Value::class)->findOneBy(['value' => $shopkey]);
        if (!$configValue || !$configValue->getShop()) {
            throw new StagingModeException('Provided shopkey is not assigned to any shop!');
        }

        $shop = $this->getShopByConfig($configValue);
        if (!$shop) {
            throw new StagingModeException('Provided shopkey is not assigned to any shop!');
        }

        return $shop;
    }

    /**
     * @param Value $config
     * @return DetachedShop|null
     */
    private function getShopByConfig(Value $config)
    {
        $shopId = $config->getShop()->getId();
        $currentShop = Shopware()->Container()->has('shop') ? Shopware()->Shop() : null;

        if ($currentShop && $shopId === $currentShop->getId()) {
            return $currentShop;
        }

        /** @var Repository $shopRepository */
        $shopRepository = Shopware()->Models()->getRepository(Shop::class);
        return $shopRepository->getActiveById($shopId);
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
        } catch (\Exception $e) {
            $isStaging = false;
        }

        return $isStaging;
    }

    /**
     * @param Shop $shop
     *
     * @return string
     */
    private function getRedirectUrl(Shop $shop)
    {
        $queryParams = '';
        if ($this->isStaging()) {
            $queryParams = '?findologic=on';
        }

        $url = rtrim($shop->getHost(), '/') . $shop->getBaseUrl();

        return sprintf(
            '%s://%s%s',
            $shop->getSecure() ? 'https' : 'http',
            $url,
            $queryParams
        );
    }
}
