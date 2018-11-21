<?php

namespace FinSearchUnified\BusinessLogic\Models;

use Doctrine\ORM\PersistentCollection;
use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\DateAdded;
use FINDOLOGIC\Export\Data\Description;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Data\Keyword;
use FINDOLOGIC\Export\Data\Name;
use FINDOLOGIC\Export\Data\Ordernumber;
use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\Export\Data\Property;
use FINDOLOGIC\Export\Data\SalesFrequency;
use FINDOLOGIC\Export\Data\Summary;
use FINDOLOGIC\Export\Data\Url;
use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\Export\Data\Image as ExportImage;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\XML\XMLExporter;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Logger;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Image;
use Shopware\Models\Category\Category;
use Shopware\Models\Customer\Group;
use Shopware\Models\Media\Media;
use Shopware\Models\Order\Detail as OrderDetail;

class FindologicArticleModel
{
    const WISHLIST_URL = 'note/add/ordernumber/';
    const COMPARE_URL = 'compare/add_article/articleID/';
    const CART_URL = 'checkout/addArticle/sAdd/';

    /**
     * @var XMLExporter
     */
    public $exporter;

    /**
     * @var Item
     */
    public $xmlArticle;

    /**
     * @var Article
     */
    public $baseArticle;

    /**
     * @var \Shopware\Models\Article\Detail
     */
    public $baseVariant;

    /**
     * @var string
     */
    public $shopKey;

    /**
     * @var PersistentCollection
     */
    public $variantArticles;

    /**
     * @var array
     */
    public $allUserGroups;

    /**
     * @var array
     */
    public $salesFrequency;

    /**
     * @var \sRewriteTable
     */
    public $seoRouter;

    /**
     * @var bool
     */
    public $shouldBeExported;

    /**
     * @var Category
     */
    public $baseCategory;

    /** @var array */
    protected $legacyStruct;

    /**
     * @var Product
     */
    protected $productStruct;

    /**
     * @var \Shopware\Components\Model\ModelRepository
     */
    protected $orderDetailRepository;

    /**
     * @var \Zend_Cache_Core
     */
    protected $cache;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * FindologicArticleModel constructor.
     *
     * @param Article $shopwareArticle
     * @param string $shopKey
     * @param array $allUserGroups
     * @param array $salesFrequency
     * @param Category $baseCategory
     */
    public function __construct(
        Article $shopwareArticle,
        $shopKey,
        array $allUserGroups,
        array $salesFrequency,
        Category $baseCategory
    ) {
        $this->shouldBeExported = false;
        $this->exporter = Exporter::create(Exporter::TYPE_XML);
        $this->xmlArticle = $this->exporter->createItem($shopwareArticle->getId());
        $this->shopKey = $shopKey;
        $this->baseCategory = $baseCategory;
        $this->baseArticle = $shopwareArticle;
        $this->baseVariant = $this->baseArticle->getMainDetail();
        $this->salesFrequency = $salesFrequency;
        $this->allUserGroups = $allUserGroups;
        $this->seoRouter = Shopware()->Container()->get('modules')->sRewriteTable();
        $this->cache = Shopware()->Container()->get('cache');
        $this->logger = Shopware()->Container()->get('pluginlogger');

        $this->setUpStruct();

        if ($this->legacyStruct) {
            // Load all variants
            $this->variantArticles = $this->baseArticle->getDetails();

            $this->orderDetailRepository = Shopware()->Container()->get('models')->getRepository(OrderDetail::class);

            // Fill out the Basedata
            $this->setArticleName();
            $this->setSummary();
            $this->setDescription();
            $this->setAddDate();
            $this->setUrls();
            $this->setKeywords();
            $this->setImages();
            $this->setSales();
            $this->setAttributes();
            $this->setUserGroups();
            $this->setPrices();
            $this->setVariantOrdernumbers();
            $this->setProperties();
        }
    }

    protected function setUpStruct()
    {
        $storefrontContextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $storefrontContextService->createShopContext(Shopware()->Shop()->getId());
        $productNumberService = Shopware()->Container()->get('shopware_storefront.product_number_service');
        $productService = Shopware()->Container()->get('shopware_storefront.product_service');

        try {
            $mainProductNumber = $productNumberService->getMainProductNumberById($this->baseArticle->getId());
            $this->productStruct = $productService->get($mainProductNumber, $context);
        } catch (\RuntimeException $exception) {
            $this->logger->warn(sprintf(
                'Skipped product with ID %d: %s',
                $this->baseArticle->getId(),
                $exception->getMessage()
            ));
        }

        if ($this->productStruct) {
            $legacyStructConverter = Shopware()->Container()->get('legacy_struct_converter');

            $this->legacyStruct = $legacyStructConverter->convertListProductStruct($this->productStruct);
        } else {
            $this->legacyStruct = [];
        }
    }

    protected function setArticleName()
    {
        if ($this->productStruct->getName()) {
            $xmlName = new Name();
            $xmlName->setValue(StaticHelper::removeControlCharacters($this->productStruct->getName()));
            $this->xmlArticle->setName($xmlName);
        }
    }

    protected function setVariantOrdernumbers()
    {

        /** @var Detail $detail */
        foreach ($this->variantArticles as $detail) {
            if (!($detail instanceof Detail)) {
                continue;
            }

            if (method_exists($detail, 'getLastStock')) {
                $lastStock = $detail->getLastStock();
            } else {
                $lastStock = $detail->getArticle()->getLastStock();
            }

            if ($detail->getInStock() < 1 && $lastStock && Shopware()->Config()->get('hideNoInStock')) {
                continue;
            }

            $this->shouldBeExported = true;
            // Remove inactive variants
            if ($detail->getActive() === 0) {
                continue;
            }
            $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getNumber()));

            if ($detail->getEan()) {
                $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getEan()));
            }

            if ($detail->getSupplierNumber()) {
                $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getSupplierNumber()));
            }
        }
    }

    protected function setSummary()
    {
        $description = StaticHelper::cleanString($this->productStruct->getShortDescription());
        if ($description) {
            $summary = new Summary();
            $summary->setValue(trim($description));
            $this->xmlArticle->setSummary($summary);
        }
    }

    protected function setDescription()
    {
        $descriptionLong = StaticHelper::cleanString($this->productStruct->getLongDescription());
        if ($descriptionLong) {
            $description = new Description();
            $description->setValue($descriptionLong);
            $this->xmlArticle->setDescription($description);
        }
    }

    protected function setPrices()
    {
        $priceArray = [];
        // variant prices per customergroup
        /** @var Detail $detail */
        foreach ($this->variantArticles as $detail) {
            if (!($detail instanceof Detail)) {
                continue;
            }
            if ($detail->getInStock() < 1) {
                continue;
            }
            if ($detail->getActive() == 1) {
                /** @var \Shopware\Models\Article\Price $price */
                foreach ($detail->getPrices() as $price) {
                    /** @var \Shopware\Models\Customer\Group $customerGroup */
                    $customerGroup = $price->getCustomerGroup();
                    if ($customerGroup) {
                        $priceArray[$customerGroup->getKey()][] = $price->getPrice();
                    }
                }
            }
        }

        // main prices per customergroup
        foreach ($this->baseVariant->getPrices() as $price) {
            if ($price->getCustomerGroup()) {
                /** @var \Shopware\Models\Customer\Group $customerGroup */
                $customerGroup = $price->getCustomerGroup();
                if ($customerGroup) {
                    $priceArray[$customerGroup->getKey()][] = $price->getPrice();
                }
            }
        }

        $tax = $this->baseArticle->getTax();

        // searching the loweset price for each customer group
        /** @var Group $userGroup */
        foreach ($this->allUserGroups as $userGroup) {
            if (array_key_exists($userGroup->getKey(), $priceArray)) {
                $price = min($priceArray[$userGroup->getKey()]);
            } else {
                $price = min($priceArray['EK']);
            }

            // Add taxes if needed
            if ($userGroup->getTax()) {
                $price *= (1 + (float) $tax->getTax() / 100);
            }

            $xmlPrice = new Price();
            $usergroupHash = StaticHelper::calculateUsergroupHash($userGroup->getKey(), $this->shopKey);
            $xmlPrice->setValue(sprintf('%.2f', $price), $usergroupHash);
            $this->xmlArticle->addPrice(sprintf('%.2f', $price), $usergroupHash);

            if ($userGroup->getKey() == 'EK') {
                $basePrice = new Price();
                $basePrice->setValue(sprintf('%.2f', $price));
                $this->xmlArticle->addPrice(sprintf('%.2f', $price));
            }
        }
    }

    protected function setAddDate()
    {
        $dateAddedValue = $this->baseArticle->getAdded();

        if ($dateAddedValue instanceof \DateTime) {
            $dateAdded = new DateAdded();
            $dateAdded->setDateValue($dateAddedValue);
            $this->xmlArticle->setDateAdded($dateAdded);
        }
    }

    protected function setUrls()
    {
        $baseLink = Shopware()->Config()->get('baseFile') . '?sViewport=detail&sArticle=' . $this->baseArticle->getId();
        $seoUrl = Shopware()->Modules()->Core()->sRewriteLink($baseLink, $this->baseArticle->getName());
        $xmlUrl = new Url();
        $xmlUrl->setValue($seoUrl);
        $this->xmlArticle->setUrl($xmlUrl);
    }

    protected function setKeywords()
    {
        $keywords = '';

        if ($this->productStruct && $this->productStruct->getKeywords()) {
            $keywords = $this->productStruct->getKeywords();

            // Check if there exist
            if (Shopware()->Shop()->getId() !== 1
                && $this->productStruct->getKeywords() === $this->baseArticle->getKeywords()) {
                $keywords = '';
            }
        }

        if ($keywords) {
            $articleKeywords = explode(',', $keywords);
            $xmlKeywords = [];
            foreach ($articleKeywords as $keyword) {
                if (self::checkIfHasValue($keyword)) {
                    $xmlKeyword = new Keyword(StaticHelper::removeControlCharacters($keyword));
                    $xmlKeywords[] = $xmlKeyword;
                }
            }
            if (count($xmlKeywords) > 0) {
                $this->xmlArticle->setAllKeywords($xmlKeywords);
            }
        }
    }

    protected static function checkIfHasValue($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value;
    }

    protected function setImages()
    {
        $articleMainImages = $this->baseArticle->getImages()->getValues();
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');
        $baseLink = Shopware()->Modules()->Core()->sRewriteLink();
        $imagesArray = [];
        $replacements = [
            '[' => '%5B',
            ']' => '%5D'
        ];

        /** @var Image $articleImage */
        foreach ($articleMainImages as $articleImage) {
            if ($articleImage->getMedia() != null) {
                /** @var Image $imageRaw */
                $imageRaw = $articleImage->getMedia();
                if (!($imageRaw instanceof Media) || $imageRaw === null) {
                    continue;
                }

                try {
                    $imageDetails = $imageRaw->getThumbnailFilePaths();
                    $imageDefault = $imageRaw->getPath();
                } catch (\Exception $ex) {
                    // Entitiy removed
                    continue;
                }

                if (count($imageDetails) > 0) {
                    $imagePath = strtr($mediaService->getUrl($imageDefault), $replacements);
                    $imagePathThumb = strtr($mediaService->getUrl(array_values($imageDetails)[0]), $replacements);
                    if ($imagePathThumb != '') {
                        $xmlImagePath = new ExportImage($imagePath, ExportImage::TYPE_DEFAULT);
                        $imagesArray[] = $xmlImagePath;
                        $xmlImageThumb = new ExportImage($imagePathThumb, ExportImage::TYPE_THUMBNAIL);
                        $imagesArray[] = $xmlImageThumb;
                    }
                }
            }
        }
        if (count($imagesArray) > 0) {
            $this->xmlArticle->setAllImages($imagesArray);
        } else {
            $noImage = $baseLink . 'templates/_default/frontend/_resources/images/no_picture.jpg';
            $xmlImage = new ExportImage($noImage);
            $imagesArray[] = $xmlImage;
            $this->xmlArticle->setAllImages($imagesArray);
        }
    }

    protected function setSales()
    {
        $orderDetailQuery = $this->orderDetailRepository->createQueryBuilder('order_details')
            ->groupBy('order_details.articleId')
            ->where('order_details.articleId = :articleId')
            ->select('sum(order_details.quantity)')
            ->setParameter('articleId', $this->baseArticle->getId());

        $articleSales = (int)$orderDetailQuery->getQuery()->getScalarResult()[0][1];

        $salesFrequency = new SalesFrequency();
        $salesFrequency->setValue($articleSales);
        $this->xmlArticle->setSalesFrequency($salesFrequency);
    }

    protected function setAttributes()
    {
        $allAttributes = [];

        $routerCategoryTemplate = Shopware()->Config()->get('routerCategoryTemplate');

        if (StaticHelper::stringEndsWith('/', $routerCategoryTemplate)) {
            $routerCategoryTemplate = '/%s/';
        } else {
            $routerCategoryTemplate = '/%s';
        }

        // Categories to XML Output
        /** @var Attribute $xmlCatUrl */
        $xmlCatProperty = new Attribute('cat');

        /** @var Attribute $xmlCatUrlProperty */
        $xmlCatUrlProperty = new Attribute('cat_url');

        $catUrlArray = [];
        $catArray = [];

        /** @var Category[] $categories */
        $categories = $this->baseArticle->getCategories();

        $id = sprintf('%s_%s', Constants::CACHE_ID_PRODUCT_STREAMS, $this->shopKey);
        $productStreams = $this->cache->load($id);

        if ($productStreams != false && array_key_exists($this->baseArticle->getId(), $productStreams)) {
            foreach ($productStreams[$this->baseArticle->getId()] as $cat) {
                $categories->add($cat);
            }
        }

        /** @var Category $category */
        foreach ($categories as $category) {
            //Hide inactive categories
            if (!$category->getActive()) {
                continue;
            }

            if (!$category->isChildOf($this->baseCategory)) {
                continue;
            }

            $catPath = $this->seoRouter->sCategoryPath($category->getId());

            while (!empty($catPath)) {
                $tempPath = sprintf($routerCategoryTemplate, implode('/', $catPath));

                if (Shopware()->Config()->get('routerToLower')) {
                    $tempPath = strtolower($tempPath);
                }

                $catUrlArray[] = $this->seoRouter->sCleanupPath($tempPath);

                array_pop($catPath);
            }

            $exportCat = StaticHelper::buildCategoryName($category->getId(), false);

            if (self::checkIfHasValue($exportCat)) {
                $catArray[] = $exportCat;
            }
        }

        $xmlCatUrlProperty->setValues(array_unique($catUrlArray));
        $xmlCatProperty->setValues(array_unique($catArray));

        /* @var array $xmlCatUrlProperty */
        $allAttributes[] = $xmlCatUrlProperty;
        /* @var array $xmlCatProperty */
        $allAttributes[] = $xmlCatProperty;

        // Supplier
        /** @var Product\Manufacturer $supplier */
        $supplier = $this->productStruct->getManufacturer();
        if ($supplier) {
            $xmlSupplier = new Attribute('brand');
            $xmlSupplier->setValues([StaticHelper::removeControlCharacters($supplier->getName())]);
            $allAttributes[] = $xmlSupplier;
        }

        // Filter Values
        if ($this->productStruct->getPropertySet()) {
            foreach ($this->productStruct->getPropertySet()->getGroups() as $group) {
                if ($group->isFilterable()) {
                    $filterValues = [];

                    foreach ($group->getOptions() as $option) {
                        if ($option->getName()) {
                            $filterValues[] = StaticHelper::removeControlCharacters($option->getName());
                        }
                    }

                    if ($filterValues) {
                        $allAttributes[] = new Attribute(
                            StaticHelper::removeControlCharacters($group->getName()),
                            $filterValues
                        );
                    }
                }
            }
        }

        $variationFilters = [];
        $storefrontContextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $storefrontContextService->createShopContext(Shopware()->Shop()->getId());
        $productService = Shopware()->Container()->get('shopware_storefront.product_service');

        // Variant configurator entries
        /** @var Detail $variant */
        foreach ($this->variantArticles as $variant) {
            if (!$variant->getActive()
                || (Shopware()->Config()->get('hideNoInStock') && $variant->getInStock() < 1)) {
                continue;
            }

            $variantStruct = $productService->get($variant->getNumber(), $context);

            if (($variantStruct instanceof Product) === false) {
                continue;
            }

            foreach ($variantStruct->getConfiguration() as $group) {
                $variationFilterValues = [];

                foreach ($group->getOptions() as $option) {
                    if (!self::checkIfHasValue($option->getName())) {
                        continue;
                    }

                    $variationFilterValues[] = StaticHelper::removeControlCharacters($option->getName());
                }

                $groupName = StaticHelper::removeControlCharacters($group->getName());

                if (array_key_exists($groupName, $variationFilters)) {
                    $variationFilters[$groupName] = array_unique(array_merge(
                        $variationFilters[$groupName],
                        $variationFilterValues
                    ));
                } else {
                    $variationFilters[$groupName] = $variationFilterValues;
                }
            }
        }

        foreach ($variationFilters as $filter => $values) {
            if (empty($values)) {
                continue;
            }

            $allAttributes[] = new Attribute($filter, $values);
        }

        // Add is new
        $newFlag = 0;
        if ($this->legacyStruct['newArticle']) {
            $newFlag = 1;
        }
        $xmlNewFlag = new Attribute('new', [$newFlag]);
        $allAttributes[] = $xmlNewFlag;

        // Add free_shipping
        if ($this->baseVariant->getShippingFree() == '') {
            $freeShipping = 0;
        } else {
            $freeShipping = $this->baseArticle->getMainDetail()->getShippingFree();
        }

        $allAttributes[] = new Attribute('free_shipping', [$freeShipping]);

        // Add sale
        $cheapestPrice = $this->productStruct->getListingPrice();
        $hasPseudoPrice = $cheapestPrice->getCalculatedPseudoPrice() > $cheapestPrice->getCalculatedPrice();
        $onSale = $this->productStruct->isCloseouts() || $hasPseudoPrice;
        $allAttributes[] = new Attribute('sale', [(int)$onSale]);
        /** @var Attribute $attribute */
        foreach ($allAttributes as $attribute) {
            $this->xmlArticle->addAttribute($attribute);
        }
    }

    protected function setUserGroups()
    {
        if (count($this->allUserGroups) > 0) {
            $userGroupArray = [];
            /** @var Group $userGroup */
            foreach ($this->allUserGroups as $userGroup) {
                if (in_array($userGroup, $this->baseArticle->getCustomerGroups()->toArray(), true)) {
                    continue;
                }

                $usergroupHash = StaticHelper::calculateUsergroupHash($this->shopKey, $userGroup->getKey());
                $userGroupAttribute = new Usergroup($usergroupHash);
                $userGroupArray[] = $userGroupAttribute;
            }
            $this->xmlArticle->setAllUsergroups($userGroupArray);
        }
    }

    protected function setProperties()
    {
        $allProperties = [];
        $rewrtieLink = Shopware()->Modules()->Core()->sRewriteLink();
        if (self::checkIfHasValue($this->baseArticle->getHighlight())) {
            $allProperties[] = new Property('highlight', ['' => $this->baseArticle->getHighlight()]);
        }
        if (self::checkIfHasValue($this->baseArticle->getTax())) {
            $allProperties[] = new Property('tax', ['' => $this->baseArticle->getTax()->getTax()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getShippingTime())) {
            $allProperties[] = new Property('shippingtime', ['' => $this->baseVariant->getShippingTime()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getPurchaseUnit())) {
            $allProperties[] = new Property('purchaseunit', ['' => $this->baseVariant->getPurchaseUnit()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getReferenceUnit())) {
            $allProperties[] = new Property('referenceunit', ['' => $this->baseVariant->getReferenceUnit()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getPackUnit())) {
            $allProperties[] = new Property('packunit', ['' => $this->baseVariant->getPackUnit()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getInStock())) {
            $allProperties[] = new Property('quantity', ['' => $this->baseVariant->getInStock()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getWeight())) {
            $allProperties[] = new Property('weight', ['' => $this->baseVariant->getWeight()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getWidth())) {
            $allProperties[] = new Property('width', ['' => $this->baseVariant->getWidth()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getHeight())) {
            $allProperties[] = new Property('height', ['' => $this->baseVariant->getHeight()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getLen())) {
            $allProperties[] = new Property('length', ['' => $this->baseVariant->getLen()]);
        }
        if (self::checkIfHasValue($this->baseVariant->getReleaseDate())) {
            $releaseDate = $this->baseVariant->getReleaseDate()->format(DATE_ATOM);
            $allProperties[] = new Property('release_date', ['' => $releaseDate]);
        }

        /** @var \Shopware\Models\Attribute\Article $attributes */
        $attributes = $this->baseArticle->getAttribute();
        if ($attributes) {
            for ($i = 1; $i < 21; $i++) {
                $value = '';
                $methodName = "getAttr$i";

                if (method_exists($attributes, $methodName)) {
                    $value = $attributes->$methodName();
                }

                if ($value instanceof \DateTime) {
                    $value = $value->format(DATE_ATOM);
                }

                if (self::checkIfHasValue($value)) {
                    $allProperties[] = new Property("attr$i", ['' => StaticHelper::removeControlCharacters($value)]);
                }
            }
        }

        $wishListUrl = $rewrtieLink . self::WISHLIST_URL . $this->baseVariant->getNumber();
        $compareUrl = $rewrtieLink . self::COMPARE_URL . $this->baseArticle->getId();
        $cartUrl = $rewrtieLink . self::CART_URL . $this->baseVariant->getNumber();

        $allProperties[] = new Property('wishlistUrl', ['' => $wishListUrl]);
        $allProperties[] = new Property('compareUrl', ['' => $compareUrl]);
        $allProperties[] = new Property('addToCartUrl', ['' => $cartUrl]);

        // Supplier
        /** @var Product\Manufacturer $supplier */
        $supplier = $this->productStruct->getManufacturer();
        if ($supplier) {
            $brandImage = $supplier->getCoverFile();

            if (self::checkIfHasValue($brandImage)) {
                $allProperties[] = new Property('brand_image', ['' => $brandImage]);
            }
        }

        $cheapestPrice = $this->productStruct->getListingPrice();

        if ($cheapestPrice->getCalculatedPseudoPrice() > $cheapestPrice->getCalculatedPrice()) {
            $allProperties[] = new Property('old_price', ['' => $cheapestPrice->getCalculatedPseudoPrice()]);
        }

        /** @var Attribute $attribute */
        foreach ($allProperties as $attribute) {
            $this->xmlArticle->addProperty($attribute);
        }
    }

    public function getXmlRepresentation()
    {
        return $this->xmlArticle;
    }
}
