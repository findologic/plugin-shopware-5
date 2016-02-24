<?php

class Shopware_Controllers_Frontend_Findologic extends Enlight_Controller_Action
{

    /**
     * @var string
     */
    private $shopKey;

    /**
     * @var integer
     */
    private $count;

    /**
     * @var integer
     */
    private $start;

    /**
     * @var integer
     */
    private $total;

    /**
     * @var Shopware\Models\Shop\Shop
     */
    private $shop;

    /**
     * @var Shopware\Components\Model\ModelManager
     */
    private $em;

    /**
     * @var sArticles
     */
    private $sArticle;

    /**
     * @var array
     */
    private $categories = array();

    /**
     * @var array
     */
    private $allUserGroups = array();

    /**
     * Executes main export.
     */
    public function indexAction()
    {
        $this->em = Shopware()->Models();
        $this->shopKey = $this->Request()->getParam('shopkey', false);
        $this->start = $this->Request()->getParam('start', false);
        $this->count = $this->Request()->getParam('count', false);

        /* @var $sArticle \sArticles */
        $this->sArticle = Shopware()->Modules()->sArticles();

        $this->shop = $this->shopExists();
        $this->validateInput();
        $this->allUserGroups = $this->getAllUserGroups();
        $this->getAllActiveCategoryIdsByShop();

        $articles = $this->getAllValidProducts();
        $xml = $this->buildXml($articles);

        header('Content-Type: application/xml; charset=utf-8');
        die($xml);
    }

    /**
     * Get all valid products, return only ones that satisfy criteria.
     *
     * @return array $result
     */
    private function getAllValidProducts()
    {
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
            ->andWhere('(d.inStock > 0 OR a.lastStock = 0)')
            ->andWhere('d.kind = 1') // meaning: field 'kind' represent variations (value: 1 is for basic article and value: 2 for variant article ).
            ->andWhere('cat.id IN (' . implode(',', array_keys($this->categories)) . ')')
            ->groupBy('a.id')
            ->having('COUNT(cg.id) < :nr_of_all_groups');  // meaning: if all user group are selected as avoid per article

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
     */
    private function validateInput()
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
     * Get shop from db if exists.
     *
     * @return Shopware\Models\Shop\Shop Shop
     */
    private function shopExists()
    {
        $conf = $this->em->getRepository('Shopware\Models\Config\Value')->findOneBy(array('value' => $this->shopKey));

        return $conf ? $conf->getShop() : null;
    }

    /**
     * Get all shop user groups.
     *
     * @return array $userGroups.
     */
    private function getAllUserGroups()
    {
        $builder = $this->em->createQueryBuilder();
        $builder->select(array('groups'))
            ->from('Shopware\Models\Customer\Group', 'groups')
            ->orderBy('groups.id');
        return $builder->getQuery()->getArrayResult();
    }

    /**
     * Get user groups by article.
     *
     * @param \Shopware\Models\Article\Article $article
     * @return array $articleGroups
     */
    private function getUserGroups($article)
    {
        $customerGroupsAvoid = array();
        foreach ($article->getCustomerGroups() as $avoid) {
            $customerGroupsAvoid[] = $avoid->getId();
        }

        $articleGroups = array();
        foreach ($this->allUserGroups as $group) {
            if (!in_array($group['id'], $customerGroupsAvoid)) {
                $articleGroups[$group['key']] = $group;
            }
        }

        return $articleGroups;
    }

    /**
     * Get all active categories for selected shop.
     *
     * @return array $categories;
     */
    private function getAllActiveCategoryIdsByShop()
    {
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder->select(array(
            'o.id',
            'o.name',
            'o.path'
        ));
        $queryBuilder->from('Shopware\Models\Category\Category', 'o');
        $queryBuilder->where('o.active = 1');
        $categories = $queryBuilder->getQuery()->getResult();

        // Set categories by ids for keys and only pass categories for selected shop.
        $categoriesByIds = array();
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
     * Set category path names and depth count.
     *
     * @param array $categoriesByIds
     * @param string $between
     * @param string $space
     * @return array $categories
     */
    private function setCategoriesPathName($categoriesByIds, $between = '_', $space = '-')
    {
        $categories = array();
        $db = Shopware()->Db();
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

            $sql = 'SELECT path FROM s_core_rewrite_urls WHERE org_path =? AND main = 1';
            $url = $db->fetchOne($sql, array('sViewport=cat&sCategory=' . $category['id']));

            $categories[$category['id']] = array(
                'depth' => count($categoryPath),
                'path' => $path,
                'pathIds' => $category['path'],
                'name' => $category['name'],
                'id' => $category['id'],
                'url' => '/' . strtolower($url),
            );
        }

        return $categories;
    }

    /**
     * Loop through all articles to build xml file.
     *
     * @param array $articles
     */
    private function buildXml($articles)
    {
        $xml = "<?xml version='1.0' ?>\n" . '<findologic version="0.9">' . '</findologic>';

        $findologic = new SimpleXMLElement($xml);
        $items = $findologic->addChild('items');
        $items->addAttribute('start', $this->start);
        $items->addAttribute('count', $this->count);
        $items->addAttribute('total', $this->total);

        /* @var $article \Shopware\Models\Article\Article */
        foreach ($articles as $article) {
            $articleGroups = $this->getUserGroups($article);
            $this->generateItemNodes($article, $items, $articleGroups);
        }

        $xml = $findologic->asXML();
        if ($this->Request()->getParam('validate', false) === 'true') {
            $this->validateXml($xml);
        }

        return $xml;
    }

    /**
     * Main function for creating xml nodes.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $items
     * @param array $articleGroups
     */
    private function generateItemNodes($article, $items, $articleGroups)
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

        $this->addSalesFrequency($article, $articleGroups, $item);

        $this->addDateAdded($article, $item);

        $this->addProperties($article, $item);

        return $items;
    }

    /**
     * Add article order number.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addOrderNumbers($article, $item)
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
     * Add article name.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addNames($article, $item)
    {
        if ($article->getName()) {
            $names = $item->addChild('names');
            $this->appendCData($names->addChild('name'), $article->getName());
        }
    }

    /**
     * Add summaries.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addSummaries($article, $item)
    {
        $summaries = $item->addChild('summaries');
        if (trim($article->getDescription())) {
            $this->appendCData($summaries->addChild('summary'), $article->getDescription());
        }
    }

    /**
     * Add descriptions.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addDescriptions($article, $item)
    {
        $descriptions = $item->addChild('descriptions');
        if (trim($article->getDescriptionLong())) {

            $this->appendCData($descriptions->addChild('description'), $article->getDescriptionLong());
        }
    }

    /**
     * Add article prices. Collect all prices from variations and group by customer group.
     * Take lowest price for each group and add tax if it is required by customer group.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $articleGroups
     * @param SimpleXMLElement $item
     */
    private function addPrices($article, $articleGroups, $item)
    {
        /* @var $detail \Shopware\Models\Article\Detail */
        $artPrices = array();
        foreach ($article->getDetails() as $detail) {
            if ($detail->getActive()) {
                foreach ($detail->getPrices() as $price) {
                    if($price->getCustomerGroup()) {
                        $artPrices[$price->getCustomerGroup()->getKey()][] = $price->getPrice();
                    }
                }
            }
        }

        foreach ($article->getMainDetail()->getPrices() as $price) {
            if($price->getCustomerGroup()) {
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
                $price = $price * (1 + (float) $tax->getTax() / 100);
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
     * Add url for article.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addUrls($article, $item)
    {
        $linkDetails = Shopware()->Config()->get('baseFile') . "?sViewport=detail&sArticle=" . $article->getId();
        $url = Shopware()->Modules()->Core()->sRewriteLink($linkDetails, $article->getName());

        $urls = $item->addChild('urls');
        $this->appendCData($urls->addChild('url'), $url);
    }

    /**
     * Add main images and thumbnails for article and its variants.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addImages($article, $item)
    {
        $imageLinks = array();
        $baseLink = Shopware()->Modules()->Core()->sRewriteLink();
        $image = $this->sArticle->sGetArticlePictures($article->getId())['src'];;
        //fetches Main cover image
        if($image){
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
            $this->appendCData($images->addChild('image'), $baseLink . 'templates/_default/frontend/_resources/images/no_picture.jpg');
        }
    }

    /**
     * Add attributes.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addAttributes($article, $item)
    {
        $attributeSet = array();

        // Add cat and cat_url
        $this->addCatAndCatUrl($article, $attributeSet);

        // Add supplier
        $this->addSupplierName($article, $attributeSet);

        // Add filters
        $this->addFilterAttributes($article, $attributeSet);

        // Add variants
        $this->addVariantAttributes($article, $attributeSet);

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
     * Add categories and categories urls.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $attributes
     */
    private function addCatAndCatUrl($article, &$attributes)
    {
        /* @var $category \Shopware\Models\Category\Category */
        foreach ($article->getCategories() as $category) {
            $cat = $this->categories[$category->getId()];
            if ($cat) {
                $pathIds = explode('|', trim($cat['pathIds'], '|'));
                $pathNames = array($cat['name']);
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
                        $attributes['cat_url'][] = $url . '/';
                    }
                }
            }
        }
    }

    /**
     * Add supplier name.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $attributes
     */
    private function addSupplierName($article, &$attributes)
    {
        $supplier = $article->getSupplier();
        $sql = "SELECT name FROM s_articles_supplier where id =?";
        $name = Shopware()->Db()->fetchOne($sql, array($supplier->getId()));
        if ($name) {
            $attributes['brand'][] = $name;
        }
    }

    /**
     * Adds all variant attributes.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $attributes
     */
    private function addVariantAttributes($article, &$attributes)
    {
        //get all active product values
        $sqlVariants = "SELECT additionalText FROM s_articles_details where articleID =?  and active = 1";
        $sqlVariants = Shopware()->Db()->fetchAll($sqlVariants, array($article->getId()));
        $temp = [];
        foreach ($sqlVariants as $res) {
            foreach (explode(' / ', $res['additionalText']) as $value) {
                $temp[] = $value;
            }
        }
        /* @var $configurator \Shopware\Models\Article\Configurator */
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
                $attributes[$key] = array_intersect($val, $temp);
            }
        }
    }

    /**
     * Add filter attributes.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $attributes
     */
    private function addFilterAttributes($article, &$attributes)
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
     * Add keywords, separated by comma.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addKeywords($article, $item)
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
     * Add usergroups to whom this article is visible.
     *
     * @param array $articleGroups
     * @param SimpleXMLElement $item
     */
    private function addUserGroups($articleGroups, $item)
    {
        if ($articleGroups) {
            $usergroups = $item->addChild('usergroups');
            foreach ($articleGroups as $group) {
                $userGroupHash = $this->userGroupToHash($this->shopKey, $group['key']);
                $this->appendCData($usergroups->addChild('usergroup'), $userGroupHash);
            }
        }
    }

    /**
     * Add sales frequencies per user group.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param array $articleGroups
     * @param SimpleXMLElement $item
     */
    private function addSalesFrequency($article, $articleGroups, $item)
    {
        // get orders order number (not articles)
        $sqlOrder = "SELECT s_order_details.ordernumber, s_user.customergroup"
            . " FROM s_order_details"
            . "     INNER JOIN s_order on s_order_details.ordernumber=s_order.ordernumber"
            . "     INNER JOIN s_user on s_order.userID=s_user.id"
            . " WHERE s_order_details.articleID =?"
            . " GROUP BY s_order_details.ordernumber, s_user.customergroup";
        $order = Shopware()->Db()->fetchAll($sqlOrder, array($article->getId()));
        $salesFreq = array();

        $salesFrequencies = $item->addChild('salesFrequencies');
        if (count($order) > 0) {
            $groupKeys = $this->getCustomerGroupKeys($articleGroups);
            //  $groupNames = $this->
            $total = 0;
            foreach ($order as $ord) {

                if (in_array($ord['customergroup'], $groupKeys)) {
                    $salesFreq[$ord['customergroup']][] = $ord['ordernumber'];
                    $total++;
                }
            }

            $this->appendCData($salesFrequencies->addChild('salesFrequency'), $total);
            foreach ($salesFreq as $key => $value) {
                $salesFrequency = $salesFrequencies->addChild('salesFrequency');
                $salesFrequency->addAttribute('usergroup', $this->userGroupToHash($this->shopKey, $key));
                $this->appendCData($salesFrequency, count($value));
            }
        }
    }

    private function getCustomerGroupKeys($groups)
    {
        $result = array();
        foreach ($groups as $group) {
            $result[] = $group['key'];
        }

        return $result;
    }

    /**
     * Add date added.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addDateAdded($article, $item)
    {
        $dateAddeds = $item->addChild('dateAddeds');
        if ($article->getAdded()) {
            $this->appendCData($dateAddeds->addChild('dateAdded'), $article->getAdded()->format(DATE_ATOM));
        }
    }

    /**
     * Add properties.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $item
     */
    private function addProperties($article, $item)
    {
        /* @var $detail \Shopware\Models\Article\Detail */
        $detail = $article->getMainDetail();
        $allProperties = $item->addChild('allProperties');
        if ($detail) {
            // add properties
            $properties = $allProperties->addChild('properties');
            $this->addProperty($properties, 'shippingfree', $detail->getShippingFree() ? 'yes' : null);
            $this->addProperty($properties, 'shippingtime', $detail->getShippingTime() ? $detail->getShippingTime() . ' days' : null);
            $this->addProperty($properties, 'purchaseunit', $detail->getPurchaseUnit());
            $this->addProperty($properties, 'referenceunit', $detail->getReferenceUnit());
            $this->addProperty($properties, 'packunit', $detail->getPackUnit());
            $this->addProperty($properties, 'highlight', $article->getHighlight());

            $this->addProperty($properties, 'wishlistUrl', Shopware()->Modules()->Core()->sRewriteLink() . 'note/add/ordernumber/' . $article->getMainDetail()->getNumber());
            $this->addProperty($properties, 'compareUrl', Shopware()->Modules()->Core()->sRewriteLink() . 'compare/add_article/articleID/' . $article->getId());
            $this->addProperty($properties, 'addToCartUrl', Shopware()->Modules()->Core()->sRewriteLink() . 'checkout/addArticle/sAdd/' . $article->getMainDetail()->getNumber());

            $this->addProperty($properties, 'unit', $detail->getUnit() && $detail->getUnit()->getId() ? $detail->getUnit()->getName() : null);
            $prices = $detail->getPrices();
            if ($prices[0]->getPseudoPrice()) {
                $price = $prices[0]->getPseudoPrice() * (1 + (float) $article->getTax()->getTax() / 100);
                $this->addProperty($properties, 'old_price', $price ? sprintf('%.2f', $price): null);
            }

            // SKIP AVOIDED GROUPS!!!
            $articlePrices = $this->em->getRepository('Shopware\Models\Article\Article')->getPricesQuery($detail->getId())->getArrayResult();
            foreach ($articlePrices as $articlePrice) {
                if ($articlePrice['customerGroup']['discount'] > 0) {
                    $allProperties = $item->addChild('allProperties');
                    $properties = $allProperties->addChild('properties');

                    $properties->addAttribute('usergroup', $this->userGroupToHash($this->shopKey, $articlePrice['customerGroup']['key']));
                    $this->addProperty($properties, 'discount', $articlePrice['customerGroup']['discount']);
                }
            }
        }

        $this->addVotes($article, $allProperties);
    }

    /** Add votes node.
     *
     * @param \Shopware\Models\Article\Article $article
     * @param SimpleXMLElement $allProperties
     */
    private function addVotes($article, $allProperties)
    {
        // add votes for an article depending on usergroups that voted, if none add to no-group
        // get votes average
        $sqlVote = "SELECT email, points FROM s_articles_vote where articleID =?";
        $voteData = Shopware()->Db()->fetchAll($sqlVote, array($article->getId()));

        $votes = array();
        if (count($voteData) > 0) {
            foreach ($voteData as $vote) {
                if ($vote['email'] !== '') {
                    $sqlGroup = 'SELECT customergroup FROM s_user' .
                        ' WHERE s_user.email=?';
                    $groupKey = Shopware()->Db()->fetchOne($sqlGroup, array($vote['email']));

                    // SKIP AVOIDED GROUPS!!!
                    $votes[$groupKey]['sum'] += $vote['points'];
                    $votes[$groupKey]['count'] += 1;
                } else {

                    $votes['no-group']['sum'] += $vote['points'];
                    $votes['no-group']['count'] += 1;
                }
            }

            $properties = $allProperties->addChild('properties');
            foreach ($votes as $key => $value) {
                $properties->addAttribute('usergroup', $this->userGroupToHash($this->shopKey, $key !== 'no-group' ? $key : 'EK'));
                $this->addProperty($properties, 'votes', $value['sum'] / $value['count']);
            }
        }
    }

    /**
     * Adds property node if value is valid.
     *
     * @param SimpleXMLElement $properties
     * @param string $key
     * @param mixed $value
     */
    private function addProperty($properties, $key, $value)
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
     * @param string $shopKey
     * @param string $userGroup
     * @return string $hash
     */
    private function userGroupToHash($shopKey, $userGroup)
    {
        return base64_encode($shopKey ^ $userGroup);
    }

    /**
     * Validates xml against findologic export schema.
     */
    private function validateXml($xml)
    {
        // Enable user error handling
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $path = 'https://raw.githubusercontent.com/FINDOLOGIC/xml-export/master/src/main/resources/findologic.xsd';
        if (!$dom->schemaValidate($path)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $return = "<br/>\n";
                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $return .= "<b>Warning $error->code</b>: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $return .= "<b>Error $error->code</b>: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $return .= "<b>Fatal Error $error->code</b>: ";
                        break;
                }

                $return .= trim($error->message);
                if ($error->file) {
                    $return .= " in <b>$error->file</b>";
                }

                echo $return . " on line <b>$error->line</b>\n";
            }

            die;
        }
    }

    /**
     * Adds CData tags.
     *
     * @param SimpleXMLElement $item
     * @param string $item
     */
    private function appendCData(SimpleXMLElement $node, $text)
    {
        $domNode = dom_import_simplexml($node);
        $domNode->appendChild($domNode->ownerDocument->createCDATASection($this->utf8Replace($text)));
    }

    private function utf8Replace($text)
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', trim($text));
    }
}
