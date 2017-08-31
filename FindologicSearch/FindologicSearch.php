<?php
namespace FindologicSearch;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;

class FindologicSearch extends Plugin
{
    /**
     * @var string
     */
    private $shopKey;

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return [
            'install' => true,
            'update' => true,
            'enable' => true
        ];
    }

    /**
     * This function updates the plugin.
     *
     * @param UpdateContext $context
     * @return bool
     */
    public function update(UpdateContext $context)
    {
        return true;
    }

    /**
     * This function installs the plugin.
     *
     * @param InstallContext $context
     * @return array
     */
    public function install(InstallContext $context)
    {
        $shopsIds = Shopware()->Db()->fetchCol(
            /** @lang mysql */
            'SELECT id FROM s_core_shops'
        );

        foreach ($shopsIds as $shopsId) {
            Shopware()->Db()->exec(
            /** @lang mysql */
                "CREATE TABLE IF NOT EXISTS findologic_search_di_product_streams_{$shopsId}
                  (id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                   stream_id int NOT NULL,
                   article_id int NOT NULL)"
            );
        }

        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * This function uninstalls the plugin.
     *
     * @param UninstallContext $context
     * @return bool
     */
    public function uninstall(UninstallContext $context)
    {
        $shopsIds = Shopware()->Db()->fetchCol(
            /** @lang mysql */
            'SELECT id FROM s_core_shops'
        );

        foreach ($shopsIds as $shopsId) {
            Shopware()->Db()->exec(
            /** @lang mysql */
                "DROP TABLE IF EXISTS findologic_search_di_product_streams_{$shopsId}"
            );
        }

        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);

        return true;
    }

    /**
     * Activates the plugin
     *
     * @param ActivateContext $context
     * @return bool Success
     * @throws \Exception
     */
    public function activate(ActivateContext $context)
    {
        return true;
    }

    /**
     * Deactivates the plugin
     *
     * @param DeactivateContext $context
     * @return bool Success
     */
    public function deactivate(DeactivateContext $context)
    {
        return true;
    }

    /**
     * Creates Events/Hooks for the plugin
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onPostDispatchFrontend',
            'Shopware_Controllers_Backend_Config_Before_Save_Config_Element' => 'onConfigSaveForm',
        ];
    }

    /**
     * Event handler for extending and passing placeholder values for script on every page.
     * Additionally passes orderID and cart amount placeholder values.
     *
     * @param \Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onPostDispatchFrontend(\Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->useFindologic($arguments)) {
            return;
        }

        $sqlGroupKey = /** @lang mysql */
            'SELECT customergroup FROM s_user where sessionID =? ';
        $groupKey = Shopware()->Db()->fetchone($sqlGroupKey, [
            Shopware()->Modules()->sSystem()->sSESSION_ID
        ]);

        $shopKey = $this->getShopKey();
        if (!empty($groupKey)) {
            $hash = base64_encode($shopKey ^ $groupKey);
        } else {
            $hash = base64_encode($shopKey ^ 'EK');
        }

        $hash = '?usergrouphash=P' . $hash;
        $mainUrl = 'https://cdn.findologic.com/static/' . strtoupper(md5($shopKey)) .  '/main.js' . $hash;

        $view = $arguments->getSubject()->View();
        $view->addTemplateDir(
            $this->getPath() . '/Views/frontend/plugins/'
        );
        $view->extendsTemplate('findologic_search/header.tpl');
        $view->assign('mainUrl', $mainUrl);
    }

    /**
     * Validates that each shop has its own shop key. Trims shop key value.
     *
     * @param \Enlight_Event_EventArgs $arguments Contains current controller as subject (magic method getSubject())
     * @return mixed
     * @throws \Exception
     */
    public function onConfigSaveForm(\Enlight_Event_EventArgs $arguments)
    {
        $params = $arguments->getSubject()->Request()->getParams();
        if ($params['name'] !== 'FindologicSearch') {
            return null;
        }

        $values = $arguments->getReturn();
        $keys = [];

        /**
         * @var int $shopId
         * @var \Shopware\Models\Config\Value $value
         */
        foreach ($values as $shopId => &$value) {
            $value->setValue(trim($value->getValue()));
            $val = $value->getValue();
            if (!$val) {
                continue;
            }

            if (isset($keys[$val])) {
                throw new \Exception('Each shop must have its own shop key!');
            }

            if (preg_match('/^[A-Z0-9]{32}$/', $val) != 1) {
                throw new \Exception('Shop key must consist of 32 characters,digits and only capital letters');
            }

            $keys[$val] = 1;
        }

        Shopware()->Container()->get('shopware.cache_manager')->clearConfigCache();

        return $values;
    }

    /**
     * Returns shop key set for current shop.
     *
     * @return bool|string
     */
    private function getShopKey()
    {
        $shopKey = Shopware()->Config()->get('findologic.shopKey');

        if ($shopKey) {
            $this->shopKey = $shopKey;
        }

        return $this->shopKey;
    }

    /**
     * Checks if findologic is enabled for request. Checks current shop key and `findologic` query string parameter.
     *
     * @param \Enlight_Controller_ActionEventArgs $arguments Contains event arguments
     * @return bool TRUE if findologic module is enabled; otherwise, FALSE.
     */
    private function useFindologic(\Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->getShopKey()) {
            return false;
        }

        $requestUrl = $arguments->getSubject()->Request()->getRequestUri();

        return strpos($requestUrl, 'findologic=off') === false;
    }
}
