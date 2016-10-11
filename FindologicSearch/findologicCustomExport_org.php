<?php

use FindologicSearch\Components\Findologic\Export;

/**
 * Example of custom export class.
 * If you need to customize export, rename it to findologicCustomExport.php and
 * make changes in this class. FINDOLOGIC module will pull it automatically.
 *
 * Class FindologicCustomExport
 */
class FindologicCustomExport extends Export
{
    /**
     * FindologicCustomExport constructor.
     * @param string $shopKey
     * @param int $start
     * @param int $count
     */
    public function __construct($shopKey, $start, $count)
    {
        parent::__construct($shopKey, $start, $count);
    }

    /**
     * Adds article order number.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     */
    protected function addOrderNumbers($article, $item)
    {
        parent::addOrderNumbers($article, $item);
    }

    /**
     * Adds article name.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     */
    protected function addNames($article, $item)
    {
        parent::addNames($article, $item);
    }

    /**
     * Adds summaries.
     *
     * @param \Shopware\Models\Article\Article $article Product used as a source for XML.
     * @param \SimpleXMLElement $item XML node to render to.
     */
    protected function addSummaries($article, $item)
    {
        parent::addSummaries($article, $item);
    }
}