<?php

namespace FinSearchAPI;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\EntityNotFoundException;
use FINDOLOGIC\Export\Exporter;
use FinSearchAPI\BusinessLogic\Models\FindologicArticleModel;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
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
	 * @var \Shopware\Models\Article\Repository
	 */
	protected $articleRepository;

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
	 * @param int $count
	 *
	 * @return xmlInformation
	 * @throws \Exception
	 */
	public function getAllProductsAsXmlArray( $start = 0, $count = 0 ) {
		$response = new xmlInformation();

		$this->customerRepository = Shopware()->Container()->get( 'models' )->getRepository( Customer::class );
		$this->articleRepository  = Shopware()->Container()->get( 'models' )->getRepository( Article::class );
		$this->orderRepository    = Shopware()->Container()->get( 'models' )->getRepository( Order::class );

		if ( $count > 0 ) {
			$countQuery = $this->articleRepository->createQueryBuilder( 'articles' )
			                                      ->select( 'count(articles.id)' );

			$response->total = $countQuery->getQuery()->getScalarResult()[0][1];

			$articlesQuery = $this->articleRepository->createQueryBuilder( 'articles' )
			                                       ->leftJoin( 'articles.details', 'details' )
			                                       ->select( 'articles' )
			                                       ->setMaxResults( $count )
			                                       ->setFirstResult( $start );
			/** @var array $allArticles */
			$allArticles = $articlesQuery->getQuery()->execute();
		}
		else {
			/** @var array $allArticles */
			$allArticles = $this->shop->getCategory()->getAllArticles();
			$response->total = count( $allArticles );
		}

		//Sales Frequency
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

			try {
				if ($article->getMainDetail()->getActive() === 0) {
					continue;
				}
			} catch (EntityNotFoundException $exception) {
				continue;
			}

			$findologicArticle = new FindologicArticleModel( $article, $this->shopKey, $allUserGroups, $articleSales );

			if ( $findologicArticle->shouldBeExported ) {
				$findologicArticles[] = $findologicArticle->getXmlRepresentation();
			}

		}

		$response->items = $findologicArticles;
		$response->count = count( $findologicArticles );

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
			$exporter->serializeItemsToFile( __DIR__ . '', $xmlArray->items, $start, $xmlArray->count, $xmlArray->total );
		} else {
			$xmlDocument = $exporter->serializeItems( $xmlArray->items, $start, $xmlArray->count, $xmlArray->total );

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

class xmlInformation {
	/** @var int */
	public $count;
	/** @var int */
	public $total;
	/** @var array */
	public $items;
}