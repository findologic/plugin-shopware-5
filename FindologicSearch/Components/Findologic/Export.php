<?php

namespace FindologicSearch\Components\Findologic;

use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\CustomerGroupCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContext;
use Shopware\Components\ProductStream\Repository;
use Shopware\Models\Article\Article;
use Cocur\Slugify\RuleProvider\DefaultRuleProvider;

class Export
{
    /**
     * @var string
     */
    protected $shopKey;

    /**
     * @var integer
     */
    protected $count;

    /**
     * @var integer
     */
    protected $start;

    /**
     * @var integer
     */
    protected $total;

    /**
     * @var \Shopware\Models\Shop\Shop
     */
    protected $shop;

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var \sArticles
     */
    protected $sArticle;

    /**
     * Cached array of categories.
     *
     * @var array
     */
    protected $categories = [];

    /**
     * Cached user groups.
     *
     * @var array
     */
    protected $allUserGroups = [];

    /**
     * Custom export class
     *
     * @var \FindologicCustomExport
     */
    protected $customExport;

    /**
     * Part of the streams table name without shop_id
     *
     * @var string
     */
    protected $streamsTable = 'findologic_search_di_product_streams_';

    /**
     * Export constructor.
     *
     * @param $shopKey
     * @param $start
     * @param $count
     */
    public function __construct($shopKey, $start, $count)
    {
        $this->em = Shopware()->Models();
        $this->shopKey = $shopKey;
        $this->start = $start;
        $this->count = $count;

        /* @var $sArticle \sArticles */
        $this->sArticle = Shopware()->Modules()->sArticles();

        $this->shop = $this->getShopIfExists();
        $this->validateInput();
        $this->allUserGroups = $this->getAllUserGroups();
        $this->prepareAllActiveCategoryIdsByShop();
    }

    /**
     * Get all valid products, return only ones that satisfy export criteria.
     *
     * @return array Array of \Shopware\Models\Article\Article objects.
     */
    public function getAllValidProducts()
    {
        $exportOutOfStock = (bool)Shopware()->Config()->get('findologic.exportOutOfStock');
        $query = $exportOutOfStock ? '(d.inStock <= 0 AND a.lastStock = 0) OR (d.inStock > 0)' : 'd.inStock > 0';

        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder
            ->select('article')
            ->from('Shopware\Models\Article\Article', 'article')
            ->orderBy('article.id');

        $subQuery = $this->em->createQueryBuilder();
        $subQuery
            ->select('a')
            ->from('Shopware\Models\Article\Article', 'a')
            ->innerJoin('a.details', 'd')
            ->leftJoin('a.customerGroups', 'cg')
            ->innerJoin('a.categories', 'cat')
            ->andWhere('a.active = 1')
            ->andWhere("a.name != ''")
            // only items that are on stock
            ->andWhere($query)
            ->andWhere('d.kind = 1') // meaning: field 'kind' represent variations
            // (value: 1 is for basic article and value: 2 for variant article ).
            ->andWhere('cat.id IN (' . implode(',', array_keys($this->categories)) . ')')
            ->groupBy('a.id')
            ->having('COUNT(cg.id) < :nr_of_all_groups'); // meaning: if all user group are selected
        // as avoid per article

        $sq = $subQuery->getQuery()->getDQL();
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in('article', $sq))
            ->setParameter('nr_of_all_groups', count($this->allUserGroups));

        $countQueryBuilder = clone $queryBuilder;
        $this->total = $countQueryBuilder
            ->select('COUNT (DISTINCT article.id) as cnt')
            ->getQuery()
            ->getSingleScalarResult();

        $queryBuilder
            ->setFirstResult($this->start)
            ->setMaxResults($this->count);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Validates whether all input parameters are supplied.
     *
     * @return void
     */
    protected function validateInput()
    {
        $message = '';
        if (!$this->shopKey) {
            $message = 'Parameter "shopkey" is missing! ';
        }

        if (!$this->shop) {
            $message .= 'Parameter "shopkey" is not configured for any store! ';
        }

        if ($this->start === false || $this->start < 0) {
            $message .= 'Parameter "start" is missing or less than 0! ';
        }

        if (!$this->count || $this->count < 1) {
            $message .= 'Parameter "count" is missing or less than 1!';
        }

        if ($message) {
            die($message);
        }
    }

    /**
     * Get all shop user groups.
     *
     * @return array User groups for current shop.
     */
    protected function getAllUserGroups()
    {
        $builder = $this->em->createQueryBuilder();
        $builder->select(array('groups'))
            ->from('Shopware\Models\Customer\Group', 'groups')
            ->orderBy('groups.id');

        return $builder->getQuery()->getArrayResult();
    }

    /**
     * Gets shop from db if exists.
     *
     * @return \Shopware\Models\Shop\Shop Shop A Shop object for supplied shop key if exists; otherwise, null.
     */
    protected function getShopIfExists()
    {
        $conf = $this->em->getRepository('Shopware\Models\Config\Value')
            ->findOneBy([
                'value' => $this->shopKey
            ]);

        return $conf ? $conf->getShop() : null;
    }

    /**
     * Gets all active categories for selected shop from database and puts it to $this->categories.
     *
     * @return void
     */
    protected function prepareAllActiveCategoryIdsByShop()
    {
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder->select([
            'o.id',
            'o.name',
            'o.path'
        ]);
        $queryBuilder->from('Shopware\Models\Category\Category', 'o');
        $queryBuilder->where('o.active = 1');
        $categories = $queryBuilder->getQuery()->getResult();

        // Set categories by ids for keys and only pass categories for selected shop.
        $categoriesByIds = [];
        $shopCategoryId = $this->shop->getCategory()->getId();
        foreach ($categories as $category) {
            if ($category['id'] == $shopCategoryId) {
                $categoriesByIds[$category['id']] = $category;
            } else {
                $categoryPath = array_filter(explode('|', $category['path']));
                foreach ($categoryPath as $catId) {
                    if ($catId == $shopCategoryId) {
                        $categoriesByIds[$category['id']] = $category;
                        break;
                    }
                }
            }
        }

        $this->categories = $this->setCategoriesPathName($categoriesByIds);
    }

    /**
     * Sets category path names and depth count.
     *
     * @param array $categoriesByIds Array of categories that should contain 'id', 'name' and 'path' keys.
     * @param string $between Text or character to be placed between category names in full path for one category
     * @param string $space Character that should be placed instead of space (' ') in path.
     * @return array $categories Array of categories with keys 'depth', 'path', 'pathIds', 'name', 'id' and 'url'.
     */
    protected function setCategoriesPathName($categoriesByIds, $between = '_', $space = '-')
    {
        $categories = [];
        $catId = $this->shop->getCategory()->getId();
        $catLanguage = strtolower($categoriesByIds[$catId]['name']);

        foreach ($categoriesByIds as $category) {
            $categoryPath = array_reverse(array_filter(explode('|', $category['path'])));
            $path = '';

            if ($category['path'] !== null) {
                foreach ($categoryPath as $p) {
                    $categoryName = str_replace(' ', $space, $categoriesByIds[$p]['name']);
                    $path .= $categoryName . $between;
                }
            }

            $path .= $category['name'];
            $path = str_replace($catLanguage, '', strtolower($path));
            $urlSEO = $this->urlSEOOptimization($path, $catLanguage);

            $categories[$category['id']] = [
                'depth' => count($categoryPath),
                'path' => $path,
                'pathIds' => $category['path'],
                'name' => $category['name'],
                'id' => $category['id'],
                'url' => $urlSEO,
            ];
        }

        return $categories;
    }

    /**
     * Method for SEO optimized URL cleaning in Shopware 5.2 system versions
     *
     * @param string $path Category path
     * @param string $catLanguage Category language
     * @return string $urlSEO
     */
    public function urlSEOOptimization($path, $catLanguage)
    {
        if ($catLanguage == 'deutsch') {
            $catLanguage = 'german';
        }

        if ($catLanguage == 'englisch') {
            $catLanguage = 'english';
        }

        $rewrite = new DefaultRuleProvider();
        $reflection = new \ReflectionClass($rewrite);
        $replaceRulesProperty = $reflection->getProperty('rules');
        $replaceRulesProperty->setAccessible(true);
        $rules = $replaceRulesProperty->getValue($rewrite);
        $rules = $rules[$catLanguage];
        $urlSEO = $this->extraRules($path);
        $urlSEO = str_replace(array_keys($rules), array_values($rules), $urlSEO);

        return $urlSEO . '/';
    }

    /**
     * Shopware additional rules
     *
     * @param string $path
     * @return string
     */
    private function extraRules($path)
    {
        // shopware rules
        $extraRules = [
            '_' => '/',
            '!' => '',
            ' - ' => '-',
            '---' => '-',
            ':' => '-',
            ',' => '-',
            "'" => '-',
            '"' => '-',
            ' ' => '-',
            '+' => '-',
            '&#351;' => 's',
            '&#350;' => 'S',
            '&#287;' => 'g',
            '&#286;' => 'G',
            '&#304;' => 'i',
            '-%' => '',
            ' & ' => '-',
            '-&-' => '-',
        ];

        $path = strtolower($path);
        foreach ($extraRules as $key => $value) {
            $path = str_replace($key, $value, $path);
        }

        return $path;
    }

    /**
     * Get user groups by article.
     *
     * @param \Shopware\Models\Article\Article $article Article to get user groups for.
     * @return array User groups for supplied article
     */
    protected function getUserGroups($article)
    {
        $customerGroupsAvoid = [];
        foreach ($article->getCustomerGroups() as $avoid) {
            $customerGroupsAvoid[] = $avoid->getId();
        }

        $articleGroups = [];
        foreach ($this->allUserGroups as $group) {
            if (!in_array($group['id'], $customerGroupsAvoid)) {
                $articleGroups[$group['key']] = $group;
            }
        }

        return $articleGroups;
    }

    /**
     * Loop through all articles to build XML file.
     *
     * @return string Built XML.
     */
    public function buildXml()
    {
        $articles = $this->getAllValidProducts();

        $xml = "<?xml version='1.0' ?>\n" . '<findologic version="1.0">' . '</findologic>';

        $findologic = new \SimpleXMLElement($xml);
        $items = $findologic->addChild('items');
        $items->addAttribute('start', $this->start);
        $items->addAttribute('count', $this->count);
        $items->addAttribute('total', $this->total);

        /* @var $article \Shopware\Models\Article\Article */
        foreach ($articles as $article) {
            $articleGroups = $this->getUserGroups($article);
            $this->renderItem($article, $items, $articleGroups);
        }

        return $findologic->asXML();
    }

    /**
     * Renders supplied $article as new child node of $items node.
     *
     * @param \Shopware\Models\Article\Article $article Product that should be serialized to XML.
     * @param \SimpleXMLElement $items Base XML node to add new product to.
     * @param array $articleGroups Customer groups for article. Some XML nodes are dependant of customer groups.
     * @return \SimpleXMLElement Returns updated $items parameter with added node for supplied article (product).
     */
    protected function renderItem($article, $items, $articleGroups)
    {
        // Create item node
        $item = $items->addChild('item');

        $item->addAttribute('id', $article->getId());

        $this->addOrderNumbers($article, $item);

        $this->addNames($article, $item);

        $this->addSummaries($article, $item);

        $this->addDescriptions($article, $item);

        $this->addPrices($article, $articleGroups, $item);

        $this->addUrls($article, $item);

        $this->addImages($article, $item);

        $this->addAttributes($article, $item);

        $this->addKeywords($article, $item);

        $this->addUserGroups($articleGroups, $item);

        $this->addSalesFrequency($article, $item);

        $this->addDateAdded($article, $item);

        $this->addProperties($article, $item);

        return $items;
    }

    /**
     * Adds article order number.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addOrderNumbers($article, $item)
    {
        $allOrderNumbers = $item->addChild('allOrdernumbers');
        $orderNumbers = $allOrderNumbers->addChild('ordernumbers');

        /* @var $detail \Shopware\Models\Article\Detail */
        foreach ($article->getDetails() as $detail) {
            if ($detail->getActive()) {
                $this->appendCData($orderNumbers->addChild('ordernumber'), $detail->getNumber());
                if ($detail->getEan()) {
                    $this->appendCData($orderNumbers->addChild('ordernumber'), $detail->getEan());
                }

                if ($detail->getSupplierNumber()) {
                    $this->appendCData($orderNumbers->addChild('ordernumber'), $detail->getSupplierNumber());
                }
            }
        }
    }

    /**
     * Adds article name.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addNames($article, $item)
    {
        if ($article->getName()) {
            $names = $item->addChild('names');
            $this->appendCData($names->addChild('name'), $article->getName());
        }
    }

    /**
     * Adds summaries.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addSummaries($article, $item)
    {
        $summaries = $item->addChild('summaries');
        if (trim($article->getDescription())) {
            $this->appendCData($summaries->addChild('summary'), $article->getDescription());
        }
    }

    /**
     * Adds descriptions.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addDescriptions($article, $item)
    {
        $descriptions = $item->addChild('descriptions');
        if (trim($article->getDescriptionLong())) {

            $this->appendCData($descriptions->addChild('description'), $article->getDescriptionLong());
        }
    }

    /**
     * Adds article prices. Collects all prices from variations and groups them by customer group.
     * Takes lowest price for each group and adds tax if it is required by customer group.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param array $articleGroups Customer groups for supplied $article.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addPrices($article, $articleGroups, $item)
    {
        /* @var $detail \Shopware\Models\Article\Detail */
        $artPrices = [];
        foreach ($article->getDetails() as $detail) {
            if ($detail->getActive()) {
                foreach ($detail->getPrices() as $price) {
                    if ($price->getCustomerGroup()) {
                        $artPrices[$price->getCustomerGroup()->getKey()][] = $price->getPrice();
                    }
                }
            }
        }

        foreach ($article->getMainDetail()->getPrices() as $price) {
            if ($price->getCustomerGroup()) {
                $artPrices[$price->getCustomerGroup()->getKey()][] = $price->getPrice();
            }
        }

        $prices = $item->addChild('prices');
        $tax = $article->getTax();
        $basePriceAdded = false;
        $price = 0;

        foreach ($articleGroups as $groupKey => $group) {
            if (array_key_exists($groupKey, $artPrices)) {
                $price = min($artPrices[$groupKey]);
            } else {
                // if price for group does not exist, pull price for default group -> default shopware behavior.
                $price = min($artPrices['EK']);
            }

            if ($group['tax']) {
                $price = $price * (1 + (float)$tax->getTax() / 100);
            }

            if ($group['key'] === 'EK') {
                $basePriceAdded = true;
                $this->appendCData($prices->addChild('price'), sprintf('%.2f', $price));
            }

            $priceNode = $prices->addChild('price');
            $priceNode->addAttribute('usergroup', $this->userGroupToHash($this->shopKey, $group['key']));
            $this->appendCData($priceNode, sprintf('%.2f', $price));
        }

        if (!$basePriceAdded && $price) {
            $this->appendCData($prices->addChild('price'), sprintf('%.2f', $price));
        }
    }

    /**
     * Adds url for article.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addUrls($article, $item)
    {
        $linkDetails = Shopware()->Config()->get('baseFile') . '?sViewport=detail&sArticle=' . $article->getId();
        $url = Shopware()->Modules()->Core()->sRewriteLink($linkDetails, $article->getName());

        $urls = $item->addChild('urls');
        $this->appendCData($urls->addChild('url'), $url);
    }

    /**
     * Adds main images and thumbnails for article and its variants. If article does not have any images,
     * renders default image placeholder.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addImages($article, $item)
    {
        $imageLinks = [];
        $baseLink = Shopware()->Modules()->Core()->sRewriteLink();
        // fetches Main cover image
        $image = $this->sArticle->sGetArticlePictures($article->getId())['src'];
        if ($image) {
            $imageLinks[] = $image;
        }

        //fetches variants images
        foreach ($article->getDetails() as $var) {
            if ($var->getActive()) {
                $varPic = $this->sArticle->sGetArticlePictures($article->getId(), false, 0, $var->getNumber());
                foreach ($varPic as $varP) {
                    $imageLinks[] = $varP['src'];
                }
            }
        }

        if (count($imageLinks) > 0) {
            $allImages = $item->addChild('allImages');
            $images = $allImages->addChild('images');
            $thumbnail = $images->addChild('image');
            $thumbnail->addAttribute('type', 'thumbnail');
            $this->appendCData($thumbnail, $imageLinks[0][0]);
            foreach ($imageLinks as $path) {
                $this->appendCData($images->addChild('image'), $path['original']);
            }
        } else {
            $allImages = $item->addChild('allImages');
            $images = $allImages->addChild('images');
            $this->appendCData(
                $images->addChild('image'),
                $baseLink . 'templates/_default/frontend/_resources/images/no_picture.jpg'
            );
        }
    }

    /**
     * Adds all attributes.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addAttributes($article, $item)
    {
        $attributeSet = [];

        // Add cat and cat_url
        $this->addCatAndCatUrl($article, $attributeSet);

        // Add supplier
        $this->addSupplierName($article, $attributeSet);

        // Add filters
        $this->addFilterAttributes($article, $attributeSet);

        // Add variants
        $this->addVariantAttributes($article, $attributeSet);

        // Add is new
        $form = Shopware()->Models()->getRepository('\Shopware\Models\Config\Form')
            ->findOneBy([
                'name' => 'Frontend76',
            ]);
        $defaultNew = Shopware()->Models()->getRepository('\Shopware\Models\Config\Element')
            ->findOneBy([
                'form' => $form,
                'name' => 'markasnew',
            ]);
        $specificValueNew = Shopware()->Models()->getRepository('\Shopware\Models\Config\Value')
            ->findOneBy([
                'element' => $defaultNew,
            ]);

        $articleAdded = $article->getAdded()->getTimestamp();

        if ($specificValueNew) {
            $articleTime = $specificValueNew->getValue() * 86400 + $articleAdded;
        } else {
            $articleTime = $defaultNew->getValue() * 86400 + $articleAdded;
        }

        $now = time();

        if ($now >= $articleTime) {
            $attributeSet['new'][] = '0';
        } else {
            $attributeSet['new'][] = '1';
        }

        // Add votes_rating
        try {
            $sArticle = Shopware()->Modules()->Articles()->sGetArticleById($article->getId());
            $votesAverage = (float)$sArticle['sVoteAverange']['averange'];
            $attributeSet['votes_rating'][] = round($votesAverage / 2);
        } catch (\Exception $e) {

        }

        // Add free_shipping
        if ($article->getMainDetail()->getShippingFree()) {
            $attributeSet['free_shipping'][] = '1';
        } else {
            $attributeSet['free_shipping'][] = '0';
        }

        // Add sale
        if ($article->getLastStock()) {
            $attributeSet['sale'][] = '1';
        } else {
            $attributeSet['sale'][] = '0';
        }

        // real rendering is done here if any of previous methods added any attribute
        if (!empty($attributeSet)) {
            $allAttributes = $item->addChild('allAttributes');
            $attributes = $allAttributes->addChild('attributes');

            foreach ($attributeSet as $key => $attributeSetVal) {
                $attribute = $attributes->addChild('attribute');

                $this->appendCData($attribute->addChild('key'), $key);
                $values = $attribute->addChild('values');
                foreach ($attributeSetVal as $value) {
                    $this->appendCData($values->addChild('value'), $value);
                }
            }
        }
    }

    /**
     * Adds categories and categories urls.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param array $attributes Array to store attributes.
     * @return void
     */
    protected function addCatAndCatUrl($article, &$attributes)
    {
        $baseUrl = $this->shop->getBaseUrl();
        $baseUrl = !empty($baseUrl) ? $baseUrl : '';
        $shopId = $this->getShop()->getId();
        $productStreams = $this->getArticleProductStream($article->getId(), $shopId);
        $allCategories = array_merge($article->getCategories()->toArray(), $productStreams);

        /* @var $category \Shopware\Models\Category\Category */
        foreach ($allCategories as $category) {
            $categoryId = is_object($category) ? $category->getId() : $category['id'];
            $this->createCatAndCatUrls($categoryId, $baseUrl, $attributes);
        }

        $attributes['cat'] = array_unique($attributes['cat']);
        $attributes['cat_url'] = array_unique($attributes['cat_url']);
    }

    /**
     * Creates 'cat' and 'cat_url' data.
     *
     * @param $categoryId
     * @param $baseUrl
     * @param $attributes
     * @return void
     */
    protected function createCatAndCatUrls($categoryId, $baseUrl, &$attributes)
    {
        $cat = $this->categories[$categoryId];
        if ($cat) {
            $pathIds = explode('|', trim($cat['pathIds'], '|'));
            $pathNames = [
                $cat['name']
            ];

            foreach ($pathIds as $pathId) {
                $pathNames[] = $this->categories[$pathId]['name'];
            }

            $reversedPathNames = array_slice(array_reverse($pathNames), 1);
            $attributes['cat'][] = implode('_', $reversedPathNames);
            $catUrls = explode('/', $cat['url']);
            $url = '';
            foreach ($catUrls as $catUrl) {
                if ($catUrl) {
                    $url .= '/' . $catUrl;
                    $attributes['cat_url'][] = $baseUrl . $url . '/';
                }
            }
        }
    }

    /**
     * Adds supplier name.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param array $attributes Array to store attributes.
     * @return void
     */
    protected function addSupplierName($article, &$attributes)
    {
        // this is done through SQL because Shopware can be in state that $supplier->getId() returns proper ID,
        // but that supplier does not exist in database so $supplier->getName() produces fatal error.
        $supplier = $article->getSupplier();
        $sql = /** @lang mysql */
            'SELECT name FROM s_articles_supplier where id =?';
        $name = Shopware()->Db()->fetchOne($sql, [
            $supplier->getId()
        ]);

        if ($name) {
            $attributes['brand'][] = $name;
        }
    }

    /**
     * Adds all variant attributes (attributes that make variants).
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param array $attributes Array to store attributes.
     * @return void
     */
    protected function addVariantAttributes($article, &$attributes)
    {
        //get all active product values
        $sqlVariants = /** @lang mysql */
            'SELECT additionalText FROM s_articles_details where articleID =?  and active = 1';
        $sqlVariants = Shopware()->Db()->fetchAll($sqlVariants, [
            $article->getId()
        ]);
        $temp = [];
        foreach ($sqlVariants as $res) {
            if (!empty($res['additionalText'])) {
                foreach (explode(' / ', $res['additionalText']) as $value) {
                    $temp[] = $value;
                }
            }
        }

        /* @var $configurator \Shopware\Models\Article\Configurator\Set */
        $configurator = $article->getConfiguratorSet();

        if ($configurator) {
            /* @var $option \Shopware\Models\Article\Configurator\Option */
            $options = $configurator->getOptions();
            $optValues = [];
            foreach ($options as $option) {
                $optValues[$option->getGroup()->getName()][] = $option->getName();
            }
            //add only options from active variants
            foreach ($optValues as $key => $val) {
                if (!empty($temp)) {
                    $attributes[$key] = array_intersect($val, $temp);
                } else {
                    $attributes[$key] = $val;
                }
            }
        }
    }

    /**
     * Adds filter attributes.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param array $attributes Array to store attributes.
     * @return void
     */
    protected function addFilterAttributes($article, &$attributes)
    {
        $filters = $article->getPropertyValues();
        /* @var $filter \Shopware\Models\Property\Value */
        foreach ($filters as $filter) {
            /* @var $option \Shopware\Models\Property\Option */
            $option = $filter->getOption();
            $attributes[$option->getName()][] = $filter->getValue();
        }
    }

    /**
     * Adds keywords, separated by comma.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addKeywords($article, $item)
    {
        $allKeywords = $item->addChild('allKeywords');
        if ($article->getKeywords() !== '') {
            $articleKeywords = explode(',', $article->getKeywords());
            $keywords = $allKeywords->addChild('keywords');
            foreach ($articleKeywords as $key) {
                $this->appendCData($keywords->addChild('keyword'), $key);
            }
        }
    }

    /**
     * Add user-groups to whom this article is visible.
     *
     * @param array $articleGroups User groups array with 'key' key.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addUserGroups($articleGroups, $item)
    {
        if ($articleGroups) {
            $userGroups = $item->addChild('usergroups');
            foreach ($articleGroups as $group) {
                $userGroupHash = $this->userGroupToHash($this->shopKey, $group['key']);
                $this->appendCData($userGroups->addChild('usergroup'), $userGroupHash);
            }
        }
    }

    /**
     * Adds sales frequencies for specific product.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addSalesFrequency($article, $item)
    {
        // get orders order number (not articles)
        $sqlOrder = /** @lang mysql */
            'SELECT s_order_details.ordernumber
              FROM s_order_details
              WHERE s_order_details.articleID = ?
              GROUP BY s_order_details.ordernumber';
        $order = Shopware()->Db()->fetchAll($sqlOrder, [
            $article->getId()
        ]);

        $salesFrequencies = $item->addChild('salesFrequencies');
        $orderCount = count($order);

        if ($orderCount > 0 || (int)$article->getPseudoSales() > (int)0) {
            $this->appendCData($salesFrequencies->addChild('salesFrequency'), $orderCount + $article->getPseudoSales());
        }
    }

    /**
     * Gets keys from customer groups.
     *
     * @param array $groups Customer groups to get keys from.
     * @return array Array of customer group keys
     * @return void
     */
    protected function getCustomerGroupKeys($groups)
    {
        $result = array();
        foreach ($groups as $group) {
            $result[] = $group['key'];
        }

        return $result;
    }

    /**
     * Adds date added.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    protected function addDateAdded($article, $item)
    {
        $dateAddeds = $item->addChild('dateAddeds');
        if ($article->getAdded()) {
            $this->appendCData($dateAddeds->addChild('dateAdded'), $article->getAdded()->format(DATE_ATOM));
        }
    }

    /**
     * Adds different properties.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     * @return void
     */
    private function addProperties($article, $item)
    {
        /* @var $detail \Shopware\Models\Article\Detail */
        $detail = $article->getMainDetail();
        if ($detail) {
            // add properties
            $rewriteLink = Shopware()->Modules()->Core()->sRewriteLink();
            $attribute = $article->getAttribute();
            $allProperties = $item->addChild('allProperties');
            $properties = $allProperties->addChild('properties');
            $this->addProperty($properties, 'shippingfree', $detail->getShippingFree() ? 1 : null);
            $this->addProperty(
                $properties,
                'shippingtime',
                $detail->getShippingTime() ? $detail->getShippingTime() : null
            );
            $this->addProperty($properties, 'purchaseunit', $detail->getPurchaseUnit());
            $this->addProperty($properties, 'referenceunit', $detail->getReferenceUnit());
            $this->addProperty($properties, 'packunit', $detail->getPackUnit());
            $this->addProperty($properties, 'highlight', $article->getHighlight());
            $this->addProperty($properties, 'quantity',
                $detail->getInStock() ? $detail->getInStock() : '');
            $this->addProperty($properties, 'tax',
                $article->getTax()->getTax() ? $article->getTax()->getTax() : '');
            $this->addProperty($properties, 'release_date',
                $detail->getReleaseDate() ? $detail->getReleaseDate()->format(DATE_ATOM) : '');
            $this->addProperty($properties, 'weight',
                $detail->getWeight() ? $detail->getWeight() : '');
            $this->addProperty($properties, 'width',
                $detail->getWidth() ? $detail->getWidth() : '');
            $this->addProperty($properties, 'height',
                $detail->getHeight() ? $detail->getHeight() : '');

            $this->addProperty($properties, 'length',
                $detail->getLen() ?$detail->getLen() : '');

            if ($attribute) {
                $this->addProperty($properties, 'attr1',
                    $attribute->getAttr1() ? $attribute->getAttr1() : '');
                $this->addProperty($properties, 'attr2',
                    $attribute->getAttr2() ? $attribute->getAttr2() : '');
                $this->addProperty($properties, 'attr3',
                    $attribute->getAttr3() ? $attribute->getAttr3() : '');
                $this->addProperty($properties, 'attr4',
                    $attribute->getAttr4() ? $attribute->getAttr4() : '');
                $this->addProperty($properties, 'attr5',
                    $attribute->getAttr5() ? $attribute->getAttr5() : '');
                $this->addProperty($properties, 'attr6',
                    $attribute->getAttr6() ? $attribute->getAttr6() : '');
                $this->addProperty($properties, 'attr4',
                    $attribute->getAttr7() ? $attribute->getAttr7() : '');
                $this->addProperty($properties, 'attr8',
                    $attribute->getAttr8() ? $attribute->getAttr8() : '');
                $this->addProperty($properties, 'attr9',
                    $attribute->getAttr9() ? $attribute->getAttr9() : '');
                $this->addProperty($properties, 'attr10',
                    $attribute->getAttr10() ? $attribute->getAttr10() : '');
                $this->addProperty($properties, 'attr11',
                    $attribute->getAttr11() ? $attribute->getAttr11() : '');
                $this->addProperty($properties, 'attr12',
                    $attribute->getAttr12() ? $attribute->getAttr12() : '');
                $this->addProperty($properties, 'attr13',
                    $attribute->getAttr13() ? $attribute->getAttr13() : '');
                $this->addProperty($properties, 'attr14',
                    $attribute->getAttr14() ? $attribute->getAttr14() : '');
                $this->addProperty($properties, 'attr15',
                    $attribute->getAttr15() ? $attribute->getAttr15() : '');
                $this->addProperty($properties, 'attr16',
                    $attribute->getAttr16() ? $attribute->getAttr16() : '');
                $this->addProperty($properties, 'attr17',
                    $attribute->getAttr17() ? $attribute->getAttr17() : '');
                $this->addProperty($properties, 'attr18',
                    $attribute->getAttr18() ? $attribute->getAttr18() : '');
                $this->addProperty($properties, 'attr19',
                    $attribute->getAttr19() ? $attribute->getAttr19() : '');
                $this->addProperty($properties, 'attr20',
                    $attribute->getAttr20() ? $attribute->getAttr20() : '');
            }

            $this->addProperty(
                $properties,
                'wishlistUrl',
                $rewriteLink . 'note/add/ordernumber/' . $article->getMainDetail()->getNumber()
            );
            $this->addProperty(
                $properties,
                'compareUrl',
                $rewriteLink . 'compare/add_article/articleID/' . $article->getId()
            );
            $this->addProperty(
                $properties,
                'addToCartUrl',
                $rewriteLink . 'checkout/addArticle/sAdd/' . $article->getMainDetail()->getNumber()
            );

            $brandImage = $article->getSupplier()->getImage();

            if ($brandImage) {
                $this->addProperty($properties, 'brand_image',
                    Shopware()->Modules()->Core()->sRewriteLink() . $brandImage);
            }

            $sql = /** @lang mysql */
                'SELECT  s.articleID,
                         s.unitID,
                         m.description
                    FROM s_articles_details as s
                    LEFT JOIN s_core_units as m ON m.id=s.unitID
                    WHERE s.articleID=?';
            $unit = Shopware()->Db()->fetchRow($sql, [
                $article->getId()
            ]);

            if ($unit) {
                $this->addProperty($properties, 'unit',
                    $unit['description'] ? $unit['description'] : null
                );
            }

            $prices = $detail->getPrices();
            if ($prices[0]->getPseudoPrice()) {
                $price = $prices[0]->getPseudoPrice() * (1 + (float)$article->getTax()->getTax() / 100);
                $this->addProperty($properties, 'old_price', $price ? sprintf('%.2f', $price) : null);
            }

            // TODO: SKIP AVOIDED GROUPS!!!
            /** @var \Shopware\Models\Article\Article $articlePrices */
            $articlePrices = $this->em->getRepository('Shopware\Models\Article\Article')
                ->getPricesQuery($detail->getId())
                ->getArrayResult();
            foreach ($articlePrices as $articlePrice) {
                if ($articlePrice['customerGroup']['discount'] > 0) {
                    $allProperties = $item->addChild('allProperties');
                    $properties = $allProperties->addChild('properties');

                    $properties->addAttribute(
                        'usergroup',
                        $this->userGroupToHash($this->shopKey, $articlePrice['customerGroup']['key'])
                    );
                    $this->addProperty($properties, 'discount', $articlePrice['customerGroup']['discount']);
                }
            }

            $this->addCustomProperties($properties);
            $this->addVotes($article, $properties);
            $this->addNew($article, $properties);
            $this->addVariantsAdditionalInfo($article, $properties);
        }
    }


    /**
     * add New
     * @param \Shopware\Models\Article\Article $article
     * @param \SimpleXMLElement $properties XML node to render to.
     * @return void
     */
    protected function addNew($article, $properties)
    {
        $form = Shopware()->Models()->getRepository('\Shopware\Models\Config\Form')
            ->findOneBy([
                'name' => 'Frontend76',
            ]);
        $defaultNew = Shopware()->Models()->getRepository('\Shopware\Models\Config\Element')
            ->findOneBy([
                'form' => $form,
                'name' => 'markasnew',
            ]);
        $specificValueNew = Shopware()->Models()->getRepository('\Shopware\Models\Config\Value')
            ->findOneBy([
                'element' => $defaultNew
            ]);

        $articleAdded = $article->getAdded()->getTimestamp();

        if ($specificValueNew) {
            $articleTime = $specificValueNew->getValue() * 86400 + $articleAdded;
        } else {
            $articleTime = $defaultNew->getValue() * 86400 + $articleAdded;
        }

        $now = time();

        if ($now >= $articleTime) {
            $this->addProperty($properties, 'new', '0');
        } else {
            $this->addProperty($properties, 'new', '1');
        }
    }

    /**
     * add Variants Additional Info
     * @param \Shopware\Models\Article\Article $article
     * @param \SimpleXMLElement $properties XML node to render to.
     * @return void
     */
    protected function addVariantsAdditionalInfo($article, $properties)
    {
        $sqlVariants = /** @lang mysql */
            'SELECT * FROM s_articles_details WHERE kind=2 AND articleID =?';
        $variantsData = Shopware()->Db()->fetchAll($sqlVariants, [
            $article->getId()
        ]);
        // 0 or 1
        if (empty($variantsData)) {
            $this->addProperty($properties, 'has_variants', 0);
        } else {
            $this->addProperty($properties, 'has_variants', 1);
            $mainPrice = $article->getMainDetail()->getPrices();
            $mainPrices = [];
            /** @var \Shopware\Models\Article\Price $price */
            foreach ($mainPrice as $price) {
                $mainPrices[$price->getCustomerGroup()->getKey()] = $price->getPrice();
            }

            /** @var \Shopware\Models\Article\Detail $variant */
            $show = 0;
            foreach ($article->getDetails() as $variant) {
                if ($variant->getId() != $article->getMainDetail()->getId()) {
                    /** @var \Shopware\Models\Article\Price $variantPrice */
                    foreach ($variant->getPrices() as $variantPrice) {
                        $group = $variantPrice->getCustomerGroup()->getKey();
                        if (!empty($mainPrices[$group]) && $mainPrices[$group] !== $variantPrice->getPrice()) {
                            $show = 1;
                            break;
                        }
                    }
                }

                if ($show === 1) {
                    break;
                }
            }

            $this->addProperty($properties, 'show_from_price', $show);
        }
    }

    /**
     * @param $properties \SimpleXMLElement $properties XML node to render to.
     * @return void
     */
    protected function addCustomProperties($properties) {
        $customExportFilePath = Shopware()->DocPath() . 'customExport.php';

        if (file_exists($customExportFilePath)) {
            require_once $customExportFilePath;

            if (class_exists('FindologicCustomExport', false)) {
                $this->customExport = $this->customExport ?: new \FindologicCustomExport($this->shopKey, $this->start, $this->count);

                if (method_exists($this->customExport, 'addCustomProperty')) {
                    $this->customExport->addCustomProperty($properties, $this);
                }
            }
        }
    }

    /**
     * Adds votes node.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $allProperties XML node to render to.
     * @return void
     */
    protected function addVotes($article, $allProperties)
    {
        // add votes for an article depending on user groups that vote. If none, add to no-group
        // get votes average
        $sqlVote = /** @lang mysql */
            'SELECT email, points FROM s_articles_vote where articleID =?';
        $voteData = Shopware()->Db()->fetchAll($sqlVote, [
            $article->getId()
        ]);
        $votes = array();
        if (count($voteData) > 0) {
            foreach ($voteData as $vote) {
                if ($vote['email'] !== '') {
                    $sqlGroup = /** @lang mysql */
                        'SELECT customergroup FROM s_user WHERE s_user.email=?';
                    $groupKey = Shopware()->Db()->fetchOne($sqlGroup, [
                        $vote['email']
                    ]);
                    // TODO: SKIP AVOIDED GROUPS!!!
                    $votes[$groupKey]['sum'] += $vote['points'];
                    $votes[$groupKey]['count'] += 1;
                } else {
                    $votes['no-group']['sum'] += $vote['points'];
                    $votes['no-group']['count'] += 1;
                }
            }
            $properties = $allProperties->addChild('properties');
            foreach ($votes as $key => $value) {
                $properties->addAttribute('usergroup',
                    $this->userGroupToHash($this->shopKey, $key !== 'no-group' ? $key : 'EK'));
                $this->addProperty($properties, 'votes', $value['sum'] / $value['count']);
            }
        }
    }

    /**
     * Adds property node if value is valid. Each node has 2 children: 'key' and 'value'.
     *
     * @param \SimpleXMLElement $properties XML node to render to.
     * @param string $key Key part of property.
     * @param mixed $value Value part of property.
     * @return void
     */
    public function addProperty($properties, $key, $value)
    {
        if ($value) {
            $property = $properties->addChild('property');
            $this->appendCData($property->addChild('key'), $key);
            $this->appendCData($property->addChild('value'), $value);
        }
    }

    /**
     * Returns hash key merged from shop key and user group.
     *
     * @param string $shopKey Shop key
     * @param string $userGroup User group key
     * @return string Hashed key.
     */
    protected function userGroupToHash($shopKey, $userGroup)
    {
        return base64_encode($shopKey ^ $userGroup);
    }

    /**
     * Wraps supplied $text with CDATA and appends to $node.
     *
     * @param \SimpleXMLElement $node XML node to append text to.
     * @param string $text Text to wrap and append.
     * @return void
     */
    protected function appendCData(\SimpleXMLElement $node, $text)
    {
        $domNode = dom_import_simplexml($node);
        $domNode->appendChild($domNode->ownerDocument->createCDATASection($this->utf8Replace($text)));
    }

    /**
     * Trims non readable utf8 empty characters from supplied string.
     *
     * @param string $text Text to check for characters.
     * @return string Trimmed string.
     */
    protected function utf8Replace($text)
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', trim($text));
    }

    /**
     * Get article product stream
     *
     * @param int $article_id
     * @param $shopId
     * @return array
     */
    public function getArticleProductStream($article_id, $shopId)
    {
        $productStreams = /** @lang mysql */
            "SELECT cat.*
                FROM s_product_streams AS ps
                LEFT JOIN s_product_streams_selection AS pss
                ON ps.id = pss.stream_id
                LEFT JOIN s_categories AS cat
                ON cat.stream_id = ps.id
                LEFT JOIN {$this->streamsTable}{$shopId} AS flsaps
                ON ps.id = flsaps.stream_id
                WHERE pss.article_id = :article_id OR flsaps.article_id = :article_id";

        $productStreamsData = Shopware()->Db()->fetchAll($productStreams, ['article_id' => $article_id]);

        // Clear empty records from data-set
        foreach ($productStreamsData as $key => $data) {
            if (empty($data['id'])) {
                unset($productStreamsData[$key]);
            }
        }

        return $productStreamsData;
    }

    /**
     * Imports data about conditional streams to 'findologic_search_di_product_streams_{$shopId}' table
     *
     * @param $shopId
     * @throws \Zend_Db_Adapter_Exception
     * @return void
     */
    public function importProductStreamsDataToDb($shopId)
    {

        $productStreamsSql = /** @lang mysql */
            'SELECT * FROM s_product_streams';
        $streams = Shopware()->Db()->fetchAll($productStreamsSql);

        $importData = [];
        foreach ($streams as $stream) {
            if (!empty($stream['conditions'])) {
                $productsTotalCount = $this->getProductStreamArticlesByConditionsTotalCount($stream['conditions'], 0, 1, $shopId);
                $offset = 0;
                $limit = 200;
                $products = [];
                while ($offset < $productsTotalCount) {
                    $products = array_merge(
                        $products,
                        $this->getProductStreamArticlesByConditions($stream['conditions'], $offset, $limit, $shopId)
                    );
                    $offset += $limit;
                }
                $importData[$stream['id']] = $products;
            }
        }

        $this->truncateProductStreamsDataTable($shopId);

        foreach($importData as $streamId => $productIds) {
            foreach ($productIds as $productId) {
                Shopware()->Db()->exec(
                    /** @lang mysql */
                    "INSERT INTO {$this->streamsTable}{$shopId} (stream_id, article_id)
                    VALUES ({$streamId}, {$productId})"
                );
            }
        }
    }

    /**
     * Checks if 'findologic_search_di_product_streams_{$shopId}' table is empty
     * Empty => true
     * Not Empty => false
     * @param $shopId
     * @return bool
     */
    public function checkIfProductStreamsTableIsEmpty($shopId)
    {
        return !(bool)Shopware()->Db()->fetchOne(
            /** @lang mysql */
            "SELECT count(*) FROM {$this->streamsTable}{$shopId}"
        );
    }

    /**
     * Checks if product streams table for current shop exists
     * If does not exists it creates one
     *
     * @param $shopId
     * @return void
     */
    public function createTableIfProductStreamTableNotExists($shopId)
    {
        Shopware()->Db()->exec(
            /** @lang mysql */
            "CREATE TABLE IF NOT EXISTS {$this->streamsTable}{$shopId}
              (id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
               stream_id int NOT NULL,
               article_id int NOT NULL)"
        );
    }

    /**
     * Truncates 'findologic_search_di_product_streams_{$shopId}' table
     *
     * @param $shopId
     * @return int
     * @throws \Zend_Db_Adapter_Exception
     */
    public function truncateProductStreamsDataTable($shopId)
    {
        return Shopware()->Db()->exec("TRUNCATE {$this->streamsTable}{$shopId}");
    }

    /**
     * Gets articles that belong to conditional product stream
     *
     * @param $conditions
     * @param $offset
     * @param $limit
     * @param $shopId
     * @return array
     */
    protected function getProductStreamArticlesByConditions($conditions, $offset, $limit, $shopId)
    {
        $result = $this->getProductStreamResults($conditions, $offset, $limit, $shopId);

        $products = array_values($result->getProducts());

        /** @var $product Article */
        $productsIds = [];
        foreach ($products as $product) {
            $productsIds[] = $product->getId();
        }

        return $productsIds;
    }

    /**
     * Gets product stream articles total count
     *
     * @param $conditions
     * @param $offset
     * @param $limit
     * @param $shopId
     * @return int
     */
    protected function getProductStreamArticlesByConditionsTotalCount($conditions, $offset, $limit, $shopId)
    {
        $result = $this->getProductStreamResults($conditions, $offset, $limit, $shopId);

        return $result->getTotalCount();
    }

    /**
     * Gets product stream results
     *
     * @param $conditions
     * @param $offset
     * @param $limit
     * @param $shopId
     * @return \Shopware\Bundle\SearchBundle\ProductSearchResult
     * @throws \Exception
     */
    private function getProductStreamResults($conditions, $offset, $limit, $shopId)
    {
        $conditions = json_decode($conditions, true);
        $shopwareInstance = Shopware();

        /** @var $criteria Criteria */
        $criteria = new Criteria();
        $criteria->offset($offset);
        $criteria->limit($limit);

        $connection = $shopwareInstance->Models()->getConnection();
        $repository = new Repository($connection);

        $conditions = $repository->unserialize($conditions);

        /** @var $condition \Shopware\Bundle\SearchBundle\ConditionInterface */
        foreach ($conditions as $condition) {
            $criteria->addCondition($condition);
        }

        $context = $this->createContext($shopId);

        $criteria->addBaseCondition(
            new CustomerGroupCondition([
                $context->getCurrentCustomerGroup()->getId()
            ])
        );

        $category = $context->getShop()
            ->getCategory()
            ->getId();

        $criteria->addBaseCondition(
            new CategoryCondition([$category])
        );

        $result = $shopwareInstance->Container()
            ->get('shopware_search.product_search')
            ->search($criteria, $context);

        return $result;
    }

    /**
     * @param $shopId
     * @param int $currencyId
     * @param int $customerGroupKey
     * @return ProductContext
     */
    private function createContext($shopId, $currencyId = null, $customerGroupKey = null)
    {
        $container = Shopware()->Container();
        /** @var \Shopware\Models\Shop\Repository $repo */
        $repo = $container->get('models')->getRepository('Shopware\Models\Shop\Shop');

        $shop = $repo->getActiveById($shopId);

        if (!$currencyId) {
            $currencyId = $shop->getCurrency()->getId();
        }

        if (!$customerGroupKey) {
            $customerGroupKey = ContextService::FALLBACK_CUSTOMER_GROUP;
        }

        return $container->get('shopware_storefront.context_service')
                ->createProductContext($shopId, $currencyId, $customerGroupKey);
    }

    /**
     * Gets total amount of articles for export
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Returns shop connected to specific shopkey
     *
     * @return \Shopware\Models\Shop\Shop
     */
    public function getShop()
    {
        return $this->shop;
    }
}