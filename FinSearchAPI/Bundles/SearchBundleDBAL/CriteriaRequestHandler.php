<?php

namespace FinSearchAPI\Bundles\SearchBundleDBAL;

use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Models\Category\Category;
use Shopware\Models\Category\Repository;

class CriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    /**
     * @param \Enlight_Controller_Request_RequestHttp                       $request
     * @param \Shopware\Bundle\SearchBundle\Criteria                        $criteria
     * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
     */
    public function handleRequest(
        \Enlight_Controller_Request_RequestHttp $request,
        \Shopware\Bundle\SearchBundle\Criteria $criteria,
        \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
    ) {

        if ($request->has('catFilter')){
            /** @var Repository $categories */
            $categories = Shopware()->Container()->get('models')->getRepository(Category::class);

        }
    }
}
