<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware_Components_Config;

class SearchQueryBuilder extends QueryBuilder
{
    public function __construct(
        InstallerService $installerService,
        Shopware_Components_Config $config,
        Client $apiClient = null,
        SearchNavigationRequest $request = null
    ) {
        $this->searchNavigationRequest = $request !== null ? $request : new SearchRequest();
        parent::__construct($installerService, $config, $apiClient);
    }

    public function addCategories(array $categories)
    {
        $categoryPath = implode('_', $categories);
        $this->addParameter('cat', $categoryPath);
    }

    protected function setDefaultParameters()
    {
        parent::setDefaultParameters();

        $query = Shopware()->Front()->Request()->getParam('sSearch', '');
        $this->searchNavigationRequest->setQuery($query);
    }
}
