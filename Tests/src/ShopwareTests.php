<?php

require __DIR__ . '/../../autoload.php';

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
     * @return mixed
     */
    public function getShopApiKey()
    {
        return 'englishShopiKey';
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
        /** @var Shopware\Models\Article\Article $article */
        $article = $this->em->getRepository('Shopware\Models\Article\Article')->find($itemId);

        $article->setActive($status == 1);
        $this->em->persist($article);
        $this->em->flush();
    }

    /**
     * Changes item stock status for item with given id to value provided in status parameter
     *
     * @param $itemId
     * @param $status
     */
    protected function changeItemStockStatus($itemId, $status)
    {
        /** @var Shopware\Models\Article\Article $article */
        $article = $this->em->getRepository('Shopware\Models\Article\Article')->find($itemId);
        $detail = $article->getMainDetail();

        $article->setLastStock($status ? 0 : 1);
        $detail->setInStock($status ? 15 : 0);

        $this->em->flush();
    }
}