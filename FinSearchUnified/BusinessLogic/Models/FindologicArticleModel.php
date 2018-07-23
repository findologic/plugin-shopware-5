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
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\XML\XMLExporter;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\ShopwareProcess;
use Shopware\Components\Api\Resource\CustomerGroup;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Image;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Shopware\Models\Customer\Group;
use Shopware\Models\Media\Media;
use Shopware\Models\Property\Option;
use Shopware\Models\Property\Value;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;

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

    /* @var Product */
    protected $productStruct;

    /**
     * FindologicArticleModel constructor.
     *
     * @param Article $shopwareArticle
     * @param string $shopKey
     * @param array $allUserGroups
     * @param array $salesFrequency
     * @param Category $baseCategory
     */
    public function __construct(Article $shopwareArticle, $shopKey, array $allUserGroups, array $salesFrequency, Category $baseCategory)
    {
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

        $this->setUpStruct();

        if ($this->legacyStruct) {
            // Load all variants
            $this->variantArticles = $this->baseArticle->getDetails();

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

    public function getXmlRepresentation()
    {
        return $this->xmlArticle;
    }

    protected function setUpStruct()
    {
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
        $productNumberService = Shopware()->Container()->get('shopware_storefront.product_number_service');
        $productService = Shopware()->Container()->get('shopware_storefront.product_service');

        $this->productStruct = $productService->get(
            $productNumberService->getMainProductNumberById($this->baseArticle->getId()),
            $context);

        $this->legacyStruct = Shopware()->Container()->get('legacy_struct_converter')->convertListProductStruct($this->productStruct);
    }

    protected function setArticleName()
    {
        if ($this->productStruct->getName()) {
            $xmlName = new Name();
            $xmlName->setValue($this->productStruct->getName());
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
            if ($detail->getInStock() < 1) {
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
            $xmlPrice->setValue(sprintf('%.2f', $price), ShopwareProcess::calculateUsergroupHash($userGroup->getKey(), $this->shopKey));
            $this->xmlArticle->addPrice(sprintf('%.2f', $price), ShopwareProcess::calculateUsergroupHash($userGroup->getKey(), $this->shopKey));

            if ($userGroup->getKey() == 'EK') {
                $basePrice = new Price();
                $basePrice->setValue(sprintf('%.2f', $price));
                $this->xmlArticle->addPrice(sprintf('%.2f', $price));
            }
        }
    }

    protected function setAddDate()
    {
        $dateAdded = new DateAdded();
        $dateAdded->setDateValue($this->baseArticle->getAdded());
        $this->xmlArticle->setDateAdded($dateAdded);
    }

    protected function setSales()
    {
        $articleId = $this->baseArticle->getId();
        $key = array_search($articleId, array_column($this->salesFrequency, 'articleId'), true);

        if ($key != false) {
            $currentSale = $this->salesFrequency[$key];
            $articleFrequency = (int) $currentSale[1];
        }

        $salesFrequency = new SalesFrequency();
        $salesFrequency->setValue($articleFrequency !== null ? $articleFrequency : 0);
        $this->xmlArticle->setSalesFrequency($salesFrequency);
    }

    protected function setUrls()
    {
        $baseLink = Shopware()->Config()->get('baseFile').'?sViewport=detail&sArticle='.$this->baseArticle->getId();
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
                    $xmlKeyword = new Keyword($keyword);
                    $xmlKeywords[] = $xmlKeyword;
                }
            }
            if (count($xmlKeywords) > 0) {
                $this->xmlArticle->setAllKeywords(['' => $xmlKeywords]);
            }
        }
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
                        $xmlImagePath = new \FINDOLOGIC\Export\Data\Image($imagePath, \FINDOLOGIC\Export\Data\Image::TYPE_DEFAULT);
                        $imagesArray[] = $xmlImagePath;
                        $xmlImageThumb = new \FINDOLOGIC\Export\Data\Image($imagePathThumb, \FINDOLOGIC\Export\Data\Image::TYPE_THUMBNAIL);
                        $imagesArray[] = $xmlImageThumb;
                    }
                }
            }
        }
        if (count($imagesArray) > 0) {
            $this->xmlArticle->setAllImages($imagesArray);
        } else {
            $noImage = $baseLink.'templates/_default/frontend/_resources/images/no_picture.jpg';
            $xmlImage = new \FINDOLOGIC\Export\Data\Image($noImage);
            $imagesArray[] = $xmlImage;
            $this->xmlArticle->setAllImages($imagesArray);
        }
    }

    protected function setAttributes()
    {
        $allAttributes = [];

        // Categories to XML Output
        /** @var Attribute $xmlCatUrl */
        $xmlCatProperty = new Attribute('cat');

        /** @var Attribute $xmlCatUrlProperty */
        $xmlCatUrlProperty = new Attribute('cat_url');

        $catUrlArray = [];
        $catArray = [];

        /** @var Category[] $categories */
        $categories = $this->baseArticle->getCategories();

        /** @var Category $category */
        foreach ($categories as $category) {
            //Hide inactive categories
            if (!$category->getActive()) {
                continue;
            }

            if (!$category->isChildOf($this->baseCategory)){
                continue;
            }

            $catPath = $this->seoRouter->sCategoryPath($category->getId());
            $tempPath = '/'.implode('/', $catPath);

            if (Shopware()->Config()->get('routerToLower')) {
                $tempPath = strtolower($tempPath);
            }

            $catUrlArray[] = $this->seoRouter->sCleanupPath($tempPath);
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
        /** @var Supplier $articleSupplier */
        $articleSupplier = $this->productStruct->getManufacturer()->getName();
        if ($articleSupplier) {
            $xmlSupplier = new Attribute('brand');
            $xmlSupplier->setValues([$articleSupplier]);
            $allAttributes[] = $xmlSupplier;
        }

        // Filter Values
        if ($this->productStruct->getPropertySet()) {
            foreach ($this->productStruct->getPropertySet()->getGroups() as $group) {
                $filterValues = [];

                foreach ($group->getOptions() as $option) {
                    if ($option->getName()) {
                        $filterValues[] = $option->getName();
                    }
                }

                if($filterValues) {
                    $allAttributes[] = new Attribute($group->getName(), $filterValues);
                }
            }
        }

        // Variant configurator entries
        /** @var Detail $variant */
        foreach ($this->variantArticles as $variant) {
            if (!$variant->getActive()
                || (Shopware()->Config()->get('hideNoInStock') && $variant->getInStock() < 1)) {
                continue;
            }

            /* @var $configuratorOption \Shopware\Models\Article\Configurator\Option */
            foreach ($variant->getConfiguratorOptions() as $configuratorOption) {

                if (!self::checkIfHasValue($configuratorOption->getName())) {
                    continue;
                }

                $xmlConfig = new Attribute(
                    $configuratorOption->getGroup()->getName(),
                    ['' => $configuratorOption->getName()]
                );
                $allAttributes[] = $xmlConfig;
            }
        }

        // Add is new
        $newFlag = 0;
        if ($this->legacyStruct['newArticle']) {
            $newFlag = 1;
        }
        $xmlNewFlag = new Attribute('new', [$newFlag]);
        $allAttributes[] = $xmlNewFlag;

        //			// Add votes_rating
        //			try {
        //				$sArticle     = Shopware()->Modules()->Articles()->sGetArticleById( $this->baseArticle->getId() );
        //				$votesAverage = (float) $sArticle['sVoteAverange']['averange'];
        //				$this->xmlArticle->addAttribute( new Attribute( 'votes_rating', [round( $votesAverage / 2 ) ?? 0] ) );
        //			} catch ( \Exception $e ) {
        //				// LOG EXCEPTION
        //			}
//
        // Add free_shipping
        $allAttributes[] = new Attribute('free_shipping', [$this->baseVariant->getShippingFree() == '' ? 0 : $this->baseArticle->getMainDetail()->getShippingFree()]);

        // Add sale
        $allAttributes[] = new Attribute('sale', [$this->baseArticle->getLastStock() == '' ? 0 : $this->baseArticle->getLastStock()]);
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
                $userGroupAttribute = new Usergroup(ShopwareProcess::calculateUsergroupHash($this->shopKey, $userGroup->getKey()));
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
            $allProperties[] = new Property('release_date', ['' => $this->baseVariant->getReleaseDate()->format(DATE_ATOM)]);
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
                    $allProperties[] = new Property("attr$i", ['' => $value]);
                }
            }
        }

        $allProperties[] = new Property('wishlistUrl', ['' => $rewrtieLink.self::WISHLIST_URL.$this->baseVariant->getNumber()]);
        $allProperties[] = new Property('compareUrl', ['' => $rewrtieLink.self::COMPARE_URL.$this->baseArticle->getId()]);
        $allProperties[] = new Property('addToCartUrl', ['' => $rewrtieLink.self::CART_URL.$this->baseVariant->getNumber()]);

        $brandImage = $this->baseArticle->getSupplier()->getImage();

        if (self::checkIfHasValue($brandImage)) {
            $allProperties[] = new Property('brand_image', ['' => $rewrtieLink.$brandImage]);
        }

        /** @var Attribute $attribute */
        foreach ($allProperties as $attribute) {
            $this->xmlArticle->addProperty($attribute);
        }
    }

    protected static function checkIfHasValue($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value;
    }
}
