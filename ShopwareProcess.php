<?php

namespace findologicDI;

use Doctrine\ORM\PersistentCollection;
use FINDOLOGIC\Export\Exporter;
use findologicDI\BusinessLogic\Models\FindologicArticleModel;
use Shopware\Models\Article\Article;
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
	 * @return array
	 * @throws \Exception
	 */
	public function getAllProductsAsXmlArray( int $start = 0, int $length = 0 ) {
		$this->customerRepository = Shopware()->Container()->get( 'models' )->getRepository( Customer::class );

		// Get all articles from selected shop
		$allArticles = $this->shop->getCategory()->getAllArticles();

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

			$findologicArticle = new FindologicArticleModel( $article, $this->shopKey, $allUserGroups, $articleSales );

			if ( $findologicArticle->shouldBeExported ) {
				array_push( $findologicArticles, $findologicArticle->getXmlRepresentation() );
			}

		}

		return $findologicArticles;
	}

	public function getFindologicXml( int $start = 0, int $length = 0, bool $save = false ) {
		$exporter = Exporter::create( Exporter::TYPE_XML );
		try {
			$xmlArray = $this->getAllProductsAsXmlArray( $start, $length );
		} catch ( \Exception $e ) {
			die( $e->getMessage() );
		}
		if ( $save ) {
			$exporter->serializeItemsToFile( __DIR__ . '', $xmlArray, 0, count( $xmlArray ), count( $xmlArray ) );
		} else {
			$xmlDocument = $exporter->serializeItems( $xmlArray, 0, count( $xmlArray ), count( $xmlArray ) );

			return $xmlDocument;
		}

	}

	/**
	 * @param string $shopKey
	 */
	public function setShopKey( string $shopKey ) {
		$this->shopKey = $shopKey;
		$configValue   = Shopware()->Models()->getRepository( 'Shopware\Models\Config\Value' )->findOneBy( [ 'value' => $shopKey ] );
		$this->shop    = $configValue ? $configValue->getShop() : null;
	}

	/* HELPER FUNCTIONS */

	public static function calculateUsergroupHash( string $shopkey, string $usergroup ) {
		$hash = base64_encode( $shopkey ^ $usergroup );

		return $hash;
	}

	public static function decryptUsergroupHash( string $shopkey, string $hash ) {
		return ( $shopkey ^ base64_decode( $hash ) );
	}


}