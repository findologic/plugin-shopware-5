<?php

namespace FinSearchUnified\BusinessLogic\Models\Article;

use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\Data;
use FinSearchUnified\Helper\StaticHelper;

class Article
{
    private $articleId;

    private $mainVariant;

    private $variants;

    private $exporter;

    private $xmlArticle;

    public function __construct($articleId, $mainVariant, $variants)
    {
        $this->exporter = Exporter::create(Exporter::TYPE_XML);
        $this->xmlArticle = $this->exporter->createItem($articleId);

        $this->articleId = $articleId;
        $this->mainVariant = $mainVariant;
        $this->variants = $variants;
    }

    public function getXml()
    {
        $this->parse();

        return $this->xmlArticle;
    }

    private function parse()
    {
        $this->parseName();
        $this->parseSummary();
        $this->parseDescription();
        $this->parsePrice();
        $this->parseReleaseDate();
        $this->parseUrl();
        $this->parseSales();
        $this->parseOrdernumbers();
        $this->parseKeywords();
        $this->parseImage();
        $this->parseProperties();
    }

    private function parseName()
    {
        $xmlName = new Data\Name();
        $xmlName->setValue($this->mainVariant['articleName']);
        $this->xmlArticle->setName($xmlName);
    }

    private function parseSummary()
    {
        $xmlName = new Data\Summary();
        $xmlName->setValue($this->mainVariant['description']);
        $this->xmlArticle->setSummary($xmlName);
    }

    private function parseDescription()
    {
        $description = new Data\Description();
        $description->setValue($this->mainVariant['description_long']);
        $this->xmlArticle->setDescription($description);
    }

    private function parsePrice()
    {
        $basePrice = new Data\Price();
        $basePrice->setValue(sprintf('%.2f', $this->mainVariant['price_numeric']));
        $this->xmlArticle->setPrice($basePrice);
    }

    private function parseReleaseDate()
    {
        if ($this->mainVariant['datum']) {
            $dateAdded = new Data\DateAdded();
            $dateAdded->setDateValue(new \DateTime($this->mainVariant['datum']));
            $this->xmlArticle->setDateAdded($dateAdded);
        }
    }

    private function parseUrl()
    {
        $baseLink = Shopware()->Config()->get('baseFile') . $this->mainVariant['linkDetails'];
        $seoUrl = Shopware()->Modules()->Core()->sRewriteLink($baseLink, $this->mainVariant['articleName']);
        $xmlUrl = new Data\Url();
        $xmlUrl->setValue($seoUrl);
        $this->xmlArticle->setUrl($xmlUrl);
    }

    private function parseSales()
    {
        $salesFrequency = new Data\SalesFrequency();
        $salesFrequency->setValue($this->mainVariant['sales']);
        $this->xmlArticle->setSalesFrequency($salesFrequency);
    }

    private function parseOrdernumbers()
    {
        $ordernumbers = [$this->mainVariant['ordernumber']];

        if ($this->mainVariant['ean']) {
            $ordernumbers[] = $this->mainVariant['ean'];
        }

        if (self::checkIfHasValue($this->mainVariant['suppliernumber'])) {
            $ordernumbers[] = $this->mainVariant['suppliernumber'];
        }

        foreach ($this->variants as $variant) {
            $ordernumbers[] = $variant['ordernumber'];

            if ($variant['ean']) {
                $ordernumbers[] = $variant['ean'];
            }

            if (self::checkIfHasValue($variant['suppliernumber'])) {
                $ordernumbers[] = $variant['suppliernumber'];
            }
        }

        foreach (array_unique($ordernumbers) as $ordernumber) {
            $this->xmlArticle->addOrdernumber(new Data\Ordernumber($ordernumber));
        }
    }

    private function parseKeywords()
    {
        foreach (explode(',', $this->mainVariant['keywords']) as $keyword) {
            if (self::checkIfHasValue($keyword)) {
                $keyword = new Data\Keyword(StaticHelper::removeControlCharacters($keyword));
                $this->xmlArticle->addKeyword($keyword);
            }
        }
    }

    private function parseImage()
    {
        if ($this->mainVariant['image']) {
            $this->xmlArticle->addImage(new Data\Image($this->mainVariant['image']['source']));

            foreach ($this->mainVariant['image']['thumbnails'] as $thumbnail) {
                $this->xmlArticle->addImage(new Data\Image($thumbnail['source'], Data\Image::TYPE_THUMBNAIL));
            }
        } else {
            $this->xmlArticle->addImage(new Data\Image(sprintf(
                "%sthemes/Frontend/Responsive/frontend/_public/src/img/no-picture.jpg",
                Shopware()->Modules()->Core()->sRewriteLink()
            )));
        }
    }

    private function parseProperties()
    {
        $this->xmlArticle->addProperty(
            new Data\Property('novelty', ['' => (int)$this->mainVariant['newArticle']])
        );

        $sale = $this->mainVariant['lastStock'] || $this->mainVariant['has_pseudoprice'];

        $this->xmlArticle->addProperty(new Data\Property('sale', ['' => (int)$sale]));
        $this->xmlArticle->addProperty(
            new Data\Property('free_shipping', ['' => (int)$this->mainVariant['shippingfree']])
        );
        $this->xmlArticle->addProperty(
            new Data\Property('availability', ['' => (int)$this->mainVariant['instock']])
        );

        if (self::checkIfHasValue($this->mainVariant['shippingtime'])) {
            $this->xmlArticle->addProperty(
                new Data\Property('delivery_time', ['' => $this->mainVariant['shippingtime']])
            );
        }

        if (isset($this->mainVariant['sVoteAverage'])) {
            $this->xmlArticle->addProperty(
                new Data\Property('rating_value', ['' => $this->mainVariant['sVoteAverage']['average']])
            );
            $this->xmlArticle->addProperty(
                new Data\Property('rating_amount', ['' => $this->mainVariant['sVoteAverage']['count']])
            );
        }

        $this->xmlArticle->addProperty(
            new Data\Property('discount', ['' => (int)$this->mainVariant['pseudopricePercent']])
        );
        $this->xmlArticle->addProperty(
            new Data\Property('basic_rate_price', ['' => $this->mainVariant['price_numeric']])
        );

        $baseRateUnit = $this->mainVariant['purchaseunit'] . $this->mainVariant['packunit'];

        $this->xmlArticle->addProperty(new Data\Property('basic_rate_unit', ['' => $baseRateUnit]));

        if (self::checkIfHasValue($this->mainVariant['supplierImg'])) {
            $this->xmlArticle->addProperty(
                new Data\Property('logo', ['' => $this->mainVariant['supplierImg']])
            );
        }

        if ($this->mainVariant['has_pseudoprice']) {
            $this->xmlArticle->addProperty(
                new Data\Property('old_price', ['' => $this->mainVariant['pseudoprice_numeric']])
            );
        }

        $addToCartUrl = Shopware()->Modules()->Core()->sRewriteLink(
            $this->mainVariant['linkBasket'],
            $this->mainVariant['articleName']
        );

        $this->xmlArticle->addProperty(new Data\Property('addToCartUrl', ['' => $addToCartUrl]));

        for ($i = 1; $i < 21; $i++) {
            $key = 'attr' . $i;
            $exists = array_key_exists($key, $this->mainVariant);

            if ($exists && self::checkIfHasValue($this->mainVariant[$key])) {
                $this->xmlArticle->addProperty(new Data\Property($key, ['' => $this->mainVariant[$key]]));
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
}