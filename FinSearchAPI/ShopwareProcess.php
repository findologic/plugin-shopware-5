<?php

namespace FinSearchAPI;

use Doctrine\ORM\PersistentCollection;
use FINDOLOGIC\Export\Exporter;
use FinSearchAPI\BusinessLogic\Models\FindologicArticleModel;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Category\Category;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;

require __DIR__ . '/vendor/autoload.php';


class ShopwareProcess {

	/**
	 * @var \Shopware\Bundle\StoreFrontBundle\Struct\ShopContext
	 */
	protected $context;

	/**
	 * @var \Shopware\Models\Customer\Repository
	 */
	protected $customerRepository;

	/**
	 * @var \Shopware\Models\Shop\Shop
	 */
	var $shop;

	/**
	 * @var string
	 */
	var $shopKey;

	/**
	 * @var \Shopware\Models\Order\Repository
	 */
	var $orderRepository;

	/**
	 * @param int $start
	 * @param int $length
	 *
	 * @return xmlInformation
	 * @throws \Exception
	 */
	public function getAllProductsAsXmlArray( $start = 0, $length = 0 ) {
		$response = new xmlInformation();

		$this->customerRepository = Shopware()->Container()->get( 'models' )->getRepository( Customer::class );

		// Get all articles from selected shop
		$allArticles = $this->shop->getCategory()->getAllArticles();

		$response->total = count($allArticles);

		if ( $length > 0 ) {
			$allArticles = array_slice( $allArticles, $start, $length );
		}


		/** @var Category[] $allCategories */
		$allCategories = $this->shop->getCategory()->getChildren();
		//Sales Frequency

		$this->orderRepository = Shopware()->Container()->get( 'models' )->getRepository( Order::class );

		$orderQuery = $this->orderRepository->createQueryBuilder( 'orders' )
		                                    ->leftJoin( 'orders.details', 'details' )
		                                    ->groupBy( 'details.articleId' )
		                                    ->select( 'details.articleId, sum(details.quantity)' );

		$articleSales = $orderQuery->getQuery()->getArrayResult();

		// Own Model for XML extraction
		$findologicArticles = array();

		/** @var array $allUserGroups */
		$allUserGroups = $this->customerRepository->getCustomerGroupsQuery()->getResult();


		/** @var Article $article */
		foreach ( $allArticles as $article ) {

			// Check if Article is Visible and Active
			if ( ! $article->getActive() ) {
				continue;
			}

			if ($article->getMainDetail() === null || !($article->getMainDetail() instanceof Detail || !$article->getMainDetail()->getActive() === 0)){
				continue;
			}

			$findologicArticle = new FindologicArticleModel( $article, $this->shopKey, $allUserGroups, $articleSales );

			if ( $findologicArticle->shouldBeExported ) {
				$findologicArticles[] = $findologicArticle->getXmlRepresentation();
			}

		}

		$response->items = $findologicArticles;
		$response->count = count($findologicArticles);

		return $response;
	}

	public function getFindologicXml( $start = 0, $length = 0, $save = false ) {
		$exporter = Exporter::create( Exporter::TYPE_XML );
		try {
			$xmlArray = $this->getAllProductsAsXmlArray( $start, $length );
		} catch ( \Exception $e ) {
			die( $e->getMessage() );
		}
		if ( $save ) {
			$exporter->serializeItemsToFile( __DIR__ . '', $xmlArray->items, 0, $xmlArray->count , $xmlArray->total );
		} else {
			$xmlDocument = $exporter->serializeItems( $xmlArray->items, 0, $xmlArray->count, $xmlArray->total );

			return $xmlDocument;
		}
	}

	/**
	 * @param string $shopKey
	 */
	public function setShopKey( $shopKey ) {
		$this->shopKey = $shopKey;
		$configValue   = Shopware()->Models()->getRepository( 'Shopware\Models\Config\Value' )->findOneBy( [ 'value' => $shopKey ] );
		$this->shop    = $configValue ? $configValue->getShop() : null;
	}

	/* HELPER FUNCTIONS */

	public static function calculateUsergroupHash( $shopkey, $usergroup ) {
		$hash = base64_encode( $shopkey ^ $usergroup );

		return $hash;
	}

	public static function decryptUsergroupHash( $shopkey, $hash ) {
		return ( $shopkey ^ base64_decode( $hash ) );
	}
}

 class xmlInformation{
	/** @var int */
	public $count;
	/** @var int */
	public $total;
	/** @var array */
	public $items;
 }