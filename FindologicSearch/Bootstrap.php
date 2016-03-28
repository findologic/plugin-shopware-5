<?php

use FindologicSearch\Components\Findologic\Helper;

class Shopware_Plugins_Frontend_FindologicSearch_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    const SHOP_KEY = 'findologic_shopkey';

    /**
     * @var string
     */
    private $shopKey;

    /**
     * @var int
     */
    private $sw;

    /**
     * @var helper
     */
    private $helper;

    /**
     * Initialise bootstrap object and set shopware version
     *
     * @param $name
     * @param null $info
     */
    public function __construct($name, $info = null)
    {
        parent::__construct($name, $info);
        $this->sw = (int)Shopware()->Config()->version;
    }

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true
        );
    }

    /**
     * Returns module label
     *
     * @return string
     */
    public function getLabel()
    {
        return 'FINDOLOGIC Search';
    }

    /**
     * Returns module version
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.0.3';
    }

    /**
     * Returns module copyright
     *
     * @return string
     */
    public function getCopyright()
    {
        return 'Copyright © ' . date('Y') . ', ' . $this->getAuthor();
    }

    /**
     * Returns module author
     *
     * @return string
     */
    public function getAuthor()
    {
        return 'FINDOLOGIC GmbH';
    }

    /**
     * Retunrs module supplier
     *
     * @return string
     */
    public function getSupplier()
    {
        return 'FINDOLOGIC GmbH';
    }

    /**
     * Returns module description
     *
     * @return string
     */
    public function getDescription()
    {
        return '_FILL_IN_.';
    }

    /**
     * Returns module support e-mail
     *
     * @return string
     */
    public function getSupport()
    {
        return 'support@findologic.com';
    }

    /**
     * Return module author link
     *
     * @return string
     */
    public function getLink()
    {
        return 'http://www.findologic.com ';
    }

    /**
     * Returns module info
     *
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'copyright' => $this->getCopyright(),
            'author' => $this->getAuthor(),
            'supplier' => $this->getSupplier(),
            'description' => $this->getDescription(),
            'support' => $this->getSupport(),
            'link' => $this->getLink()
        );
    }

    /**
     * This function updates the plugin.
     *
     * @param string $oldVersion
     * @return bool
     */
    public function update($oldVersion)
    {
        switch($oldVersion) {
            case '1.0.1':
            case '1.0.2':
                $this->subscribeEvent(
                    'Enlight_Controller_Action_PostDispatch_Frontend_Listing', 'onPostDispatchListing'
                );
                break;
            default:
                return false;
        }

        return true;
    }

    /**
     * This function installs the plugin.
     *
     * @return array
     */
    public function install()
    {
        $this->createConfiguration();

        $this->registerControllers();
        $this->registerEvents();
        return array('success' => true, 'invalidateCache' => array('frontend', 'backend'));
    }

    /**
     * Uninstalling module
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * The afterInit function registers the custom plugin models and namespaces.
     */
    public function afterInit()
    {
        $ds = DIRECTORY_SEPARATOR;
        $this->registerCustomModels();
        $this->Application()->Loader()->registerNamespace('FindologicSearch', $this->Path());
        $this->Application()->Loader()->registerNamespace('FindologicSearch\Components\Findologic', $this->Path() . $ds . 'Components' . $ds . 'Findologic');
        $this->helper = new Helper($this);
    }

    /**
     * Register module events
     */
    private function registerEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend', 'onPostDispatchFrontend'
        );
        $this->subscribeEvent(
            'Shopware_Controllers_Backend_Config_Before_Save_Config_Element', 'onConfigSaveForm'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Detail', 'onPostDispatchDetail'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Listing', 'onPostDispatchListing'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_Checkout_Finish', 'onCheckoutFinish'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_Checkout_Cart', 'onCheckoutConfirm'
        );
        $this->subscribeEvent(
            'Enlight_Controller_Action_Frontend_Checkout_Confirm', 'onCheckoutConfirm'
        );
        if ($this->sw === 5) {
            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_ajaxCart', 'onCheckoutConfirm'
            );
        } else {
            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_addArticle', 'onCheckoutConfirm'
            );
            $this->subscribeEvent(
                'Enlight_Controller_Action_Frontend_Checkout_ajaxAddArticle', 'onCheckoutConfirm'
            );
        }
    }

    /**
     * Event handler for article detail page extending and passing placeholder values for article detail script.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onPostDispatchDetail(Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->useFindologic($arguments)) {
            return;
        }

        /** @var Enlight_Controller_Action $controller */
        $controller = $arguments->getSubject();
        $view = $controller->View();
        $params = $controller->Request()->getParams();
        $id = $params['sArticle'];
        if(!$id){
            $orderNumber = $params['ordernumber'];
            $articleByID = Shopware()->Modules()->Articles()->sGetArticleIdByOrderNumber($orderNumber);
        } else {
            $articleByID = Shopware()->Modules()->sArticles()->sGetArticleById($id);
        }

        $this->extendTemplate($view, 'detail');
        $view->assign('PRODUCT_ORDERNUMBER', $articleByID['ordernumber']);
        $view->assign('PRODUCT_TITLE', $articleByID['articleName']);
        $view->assign('PRODUCT_CATEGORY', $this->helper->getCategories($articleByID['categoryID']));
        $view->assign('PRODUCT_PRICE', str_replace(',', '.', $articleByID['price']));
    }

    /**
     * Event handler used for category tracking.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onPostDispatchListing(Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->useFindologic($arguments)) {
            return;
        }

        $controller = $arguments->getSubject();
        $view = $controller->View();
        $categorySlug = $controller->Request()->getParam('sCategory');
        $categoryContent = Shopware()->Modules()->Categories()->sGetCategoryContent($categorySlug);
        if (isset($categoryContent['id'])) {
            $this->extendTemplate($view, 'listing');
            $view->assign('CATEGORY_PATH', $this->helper->getCategories($categoryContent['id']));
        }
    }

    /**
     * Event handler for extending and passing placeholder values for script on every page.
     * Additionally passes orderID and cart amount placeholder values.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onPostDispatchFrontend(Enlight_Controller_ActionEventArgs $arguments)
    {
        $controller = $arguments->getSubject();
        $view = $controller->View();

        // Add template directory
        $view->addTemplateDir(
            $this->Path() . 'Views/'
        );

        if (!$this->useFindologic($arguments)) {
            return;
        }

        $sqlGroupKey = "SELECT customergroup FROM s_user where sessionID =? ";
        $groupKey = Shopware()->Db()->fetchone($sqlGroupKey, array(Shopware()->Modules()->sSystem()->sSESSION_ID));
        $shopKey = $this->getShopKey();
        if (!empty($groupKey)) {
            $hash = base64_encode($shopKey ^ $groupKey);
        } else {
            $hash = base64_encode($shopKey ^ 'EK');
        }

        $this->extendTemplate($view, 'index');

        // $placeholder1 - upper case md5-encoded shopkey, $placeholder2 - usergrouphash of specific user
        $view->assign('placeholder1', strtoupper(md5($shopKey)));
        $view->assign('placeholder2', $hash);
    }

    /**
     * Event handler for cart actions extending and passing placeholder values for cart tracking script.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onCheckoutConfirm(Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->useFindologic($arguments)) {
            return;
        }

        $controller = $arguments->getSubject();
        $view = $controller->View();
        $order = array();

        $basket = Shopware()->Modules()->Basket()->sGetBasket();
        $cart = $basket['content'];

        // prepare all ordered items for tracking
        foreach ($cart as $cartItem) {
            if (($cartItem['ordernumber'] !== 'SHIPPINGDISCOUNT') && ($cartItem['ordernumber'] !== 'sw-discount')) {
                $order[] = array(
                    'productordernumber' => $cartItem['ordernumber'],
                    'productname' => $cartItem['articlename'],
                    'productcategories' => array_unique($this->helper->getCartCategories($cartItem['articleID'])),
                    'productprice' => $cartItem['priceNumeric'],
                    'productquantity' => $cartItem['quantity'],
                );
            }
        }

        $this->extendTemplate($view, 'cart');
        $this->extendTemplate($view, 'ajax_cart');
        $view->assign('order', $order);

        $cartAmount = 0;
        // get right total cart amount
        if ($this->sw === 4) {
            if ($view->sBasket['AmountWithTaxNumeric'] && $view->sBasket['AmountWithTaxNumeric'] != $cartAmount) {
                $cartAmount = $view->sBasket['AmountWithTaxNumeric'];
            } else if ($view->sBasket['sAmount'] && $view->sBasket['sAmount'] != $cartAmount) {
                $cartAmount = $view->sBasket['sAmount'];
            } else if (Shopware()->Modules()->Basket()->sGetAmount()['totalAmount']) {
                $cartAmount = Shopware()->Modules()->Basket()->sGetAmount()['totalAmount'];
            }
        } else {
            $basket = Shopware()->Modules()->Basket()->sGetBasket();
            if ($view->sBasket['AmountWithTaxNumeric'] && $view->sBasket['AmountWithTaxNumeric'] != $cartAmount) {
                $cartAmount = $view->sBasket['AmountWithTaxNumeric'];
            } else if ($basket['Amount'] && $basket['Amount'] != $cartAmount) {
                $cartAmount = $basket['Amount'];
            }
        }

        $view->assign('CART_AMOUNT', $cartAmount);
    }

    /**
     * Event handler for confirmation action extending and passing placeholder values for confirmation tracking script.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains current controller as subject (method getSubject())
     */
    public function onCheckoutFinish(Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->useFindologic($arguments)) {
            return;
        }

        $controller = $arguments->getSubject();
        $view = $controller->View();

        $order = array();
        $basket = Shopware()->Session()->sOrderVariables['sBasket'];
        $content = $basket['content'];
        $totalBasket = $basket['AmountWithTaxNumeric'] ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];

        // calculate totals for order
        $orderDetails = array(
            'subtotal' => $totalBasket - $basket['sShippingcostsWithTax'],
            'tax' => $basket['sAmountTax'],
            'total' => $totalBasket,
            'shipping' => $basket['sShippingcostsWithTax'],
            'discount' => 0,
        );

        // get info about each ordered item
        foreach ($content as $orderItem) {
            if ($orderItem['ordernumber'] !== 'SHIPPINGDISCOUNT' && $orderItem['ordernumber'] !== 'sw-discount') {
                $order[] = array(
                    'productordernumber' => $orderItem['ordernumber'],
                    'productname' => $orderItem['articlename'],
                    'productcategories' => array_unique($this->helper->getCartCategories($orderItem['articleID'])),
                    'productprice' => $orderItem['price'],
                    'productquantity' => $orderItem['quantity'],
                );
            } else {
                $orderDetails['discount'] = $orderItem['amountWithTax'];
            }
        }

        $this->extendTemplate($view, 'confirmation');
        $assign = $view->getAssign();
        $orderNumber = $assign['sOrderNumber'];
        if ($orderNumber) {
            $view->assign('ORDER_ID', $orderNumber);
        }

        $view->assign('ORDER_TOTAL', $orderDetails['total']);
        $view->assign('productsinorder', $order);
        $view->assign('ORDER_SUB_TOTAL', $orderDetails['subtotal']);
        $view->assign('ORDER_TAX_AMOUNT', $orderDetails['tax']);
        $view->assign('ORDER_DISCOUNT', $orderDetails['discount']);
        $view->assign('ORDER_SHIPPING_AMOUNT', $orderDetails['shipping']);
    }

    /**
     * Registers module controllers.
     *
     * @throws Exception
     */
    public function registerControllers()
    {
        $this->registerController('Frontend', 'Findologic');
    }

    /**
     * Validates that each shop has its own shop key. Trims shop key value.
     *
     * @param Enlight_Event_EventArgs $arguments Contains current controller as subject (magic method getSubject())
     * @return mixed
     * @throws Exception
     */
    public function onConfigSaveForm(Enlight_Event_EventArgs $arguments)
    {
        $params = $arguments->getSubject()->Request()->getParams();
        if ($params['name'] !== 'FindologicSearch') {
            return null;
        }

        $values = $arguments->getReturn();
        $keys = array();

        /**
         * @var int $shopId
         * @var Shopware\Models\Config\Value $value
         */
        foreach($values as $shopId => &$value) {
            $value->setValue(trim($value->getValue()));
            $val = $value->getValue();
            if (!$val) {
                continue;
            }

            if (isset($keys[$val])) {
                throw new \Exception('Each shop must have its own shop key!');
            }

            $keys[$val] = 1;
        }

        return $values;
    }

    /**
     * Creates plugin configuration fields.
     */
    public function createConfiguration()
    {
        $form = $this->Form();
        $form->setElement('text', self::SHOP_KEY, array(
                'label' => 'FINDOLOGIC Shop Key',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP,
                'description' => 'Enter shopkey from FINDOLOGIC',
                'required' => true,
            )
        );
    }

    /**
     * Extends passed template with modules header.tpl
     *
     * @param Enlight_View_Default $view
     * @param string $controller
     */
    private function extendTemplate(Enlight_View_Default $view, $controller)
    {
        $view->extendsTemplate("frontend/findologic_search_sw{$this->sw}/$controller/header.tpl");
    }

    /**
     * Returns shop key set for current shop.
     *
     * @return bool|string
     */
    private function getShopKey()
    {
        if (!$this->shopKey) {
            $shopName = Shopware()->Shop()->getName();
            $config = Shopware()->Plugins()->Frontend()->FindologicSearch()->Config();
            $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')
                ->findOneBy(array('name' => $shopName));
            $shopId = $shop->getId();

            $configShop = Shopware()->Models()->getRepository('Shopware\Models\Config\Value')
                ->findOneBy(array('value' => $config[self::SHOP_KEY]));
            if ($configShop && $configShop->getShop()->getId() == $shopId) {
                $this->shopKey = trim($config[self::SHOP_KEY]);
            } else {
                $this->shopKey = false;
            }
        }

        return $this->shopKey;
    }

    /**
     * Checks if findologic is enabled for request. Checks current shop key and `findologic` query string parameter.
     *
     * @param Enlight_Controller_ActionEventArgs $arguments Contains event arguments
     * @return bool TRUE if findologic module is enabled; otherwise, FALSE.
     */
    private function useFindologic(Enlight_Controller_ActionEventArgs $arguments)
    {
        if (!$this->getShopKey()) {
            return false;
        }

        $requestUrl = $arguments->getSubject()->Request()->getRequestUri();
        return strpos($requestUrl, 'findologic=off') === false;
    }
}
