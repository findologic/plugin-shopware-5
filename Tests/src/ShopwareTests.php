<?php

require __DIR__ . '/../../vendor/autoload.php';

use Shopware\Kernel;
use Symfony\Component\HttpFoundation\Request;

$environment = getenv('SHOPWARE_ENV') ?: getenv('REDIRECT_SHOPWARE_ENV') ?: 'production';

$kernel = new Kernel($environment, $environment !== 'production');
$kernel->handle(Request::createFromGlobals());

class ShopwareTests extends \Soprex\Findologic\Modules\Tests\Base\AbstractTestBase
{

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    /** @var int */
    private $version;

    public function setUp()
    {
        parent::setUp();
        $this->em = Shopware()->Models();
        $this->version = (int)Shopware()->Config()->version;
    }

    /**
     * Returns test shop url with api endpoint
     * e.g. 'http://magento.dev.soprex.com/findologic/export'
     *
     * @return mixed
     */
    protected function getShopExportUrl()
    {
        return "http://shopware{$this->version}.vagrant.box/findologic/index?";
    }

    /**
     * Returns shop export api key
     *
     * @param string $languageName
     * @return string
     */
    public function getShopApiKey($languageName = 'english')
    {
        $languages = [
            'english' => 'EN123456EN123456EN123456EN123456',
            'german' => 'EN123456EN123456EN123456EN123456',
        ];

        return $languages[$languageName];
    }

    /**
     * Returns number of expected items in export
     *
     * @return int
     */
    public function getProductCount()
    {
        return $this->version == 5 ? 162 : 106;
    }

    /**
     * Changes item active status for item with given id to value provided in status parameter
     *
     * @param $itemId
     * @param $status
     */
    protected function changeItemActiveStatus($itemId, $status)
    {
//        /** @var Shopware\Models\Article\Article $article */
//        $article = $this->em->getRepository('Shopware\Models\Article\Article')->find($itemId);
//
//        $article->setActive($status == 1);
//        $this->em->persist($article);
//        $this->em->flush();
    }

    /**
     * Changes item stock status for item with given id to value provided in status parameter
     *
     * @param $itemId
     * @param $status
     */
    protected function changeItemStockStatus($itemId, $status)
    {
//        /** @var Shopware\Models\Article\Article $article */
//        $article = $this->em->getRepository('Shopware\Models\Article\Article')->find($itemId);
//        $detail = $article->getMainDetail();
//
//        $article->setLastStock($status ? 0 : 1);
//        $detail->setInStock($status ? 15 : 0);
//
//        $this->em->flush();
    }

    /**
     * Returns index of thumbnail image url in export
     *
     * @return int
     */
    protected function getThumbnailIndex()
    {
//        return 0;
    }

    /**
     * Starts mysql transaction
     *
     * @return bool
     */
    protected function startTransaction()
    {

    }

    /**
     * Commits mysql transaction
     *
     * @return bool
     */
    protected function commitTransaction()
    {

    }

    /**
     * RollBacks mysql transaction
     *
     * @return bool
     */
    protected function rollbackTransaction()
    {

    }

    /**
     * Returns position of the product in export that has sales frequency
     *
     * @return int
     */
    protected function getExportPositionOfTheProductWithSalesFrequency()
    {

    }

    /**
     * Returns properties of the product by product id
     *
     * @param $productId
     * @return array
     */
    protected function getProductProperties($productId)
    {

    }

    /**
     * Returns product date added by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductDateAdded($productId)
    {

    }

    /**
     * Returns sales frequency by product id
     *
     * @param $productId
     * @return int
     */
    protected function getProductSalesFrequency($productId)
    {

    }

    /**
     * Returns all product user groups
     *
     * @return array
     */
    protected function getUserGroups()
    {

    }

    /**
     * Returns all product keywords by product id
     *
     * @param $productId
     * @return array
     */
    protected function getProductKeywords($productId)
    {

    }

    /**
     * Returns all product attributes by product id
     *
     * @param $productId
     * @return array
     */
    protected function getProductAttributes($productId)
    {

    }

    /**
     * Returns all product images by product id
     *
     * @param $productId
     * @return array
     */
    protected function getProductImages($productId)
    {

    }

    /**
     * Returns product thumbnail image by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductThumbnailUrl($productId)
    {

    }

    /**
     * Returns product url by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductUrl($productId)
    {

    }

    /**
     * Returns product price by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductPrice($productId)
    {

    }

    /**
     * Returns product description by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductDescription($productId)
    {

    }

    /**
     * Return product summary by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductSummary($productId)
    {

    }

    /**
     * Returns product title by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductTitle($productId)
    {

    }

    /**
     * Returns product order number by product id
     *
     * @param $productId
     * @return string
     */
    protected function getProductOrderNumber($productId)
    {

    }

    /**
     * Returns short description of the product by product id in english and german language
     *
     * @param $productId
     * @return array
     */
    protected function getProductShortDescription($productId)
    {

    }
}