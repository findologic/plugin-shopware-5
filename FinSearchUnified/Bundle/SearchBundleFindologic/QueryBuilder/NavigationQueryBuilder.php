<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware_Components_Config;

class NavigationQueryBuilder extends QueryBuilder
{
    public function __construct(
        InstallerService $installerService,
        Shopware_Components_Config $config,
        Client $apiClient = null,
        SearchNavigationRequest $request = null
    ) {
        $this->searchNavigationRequest = $request !== null ? $request : new NavigationRequest();
        parent::__construct($installerService, $config, $apiClient);
    }

    public function addCategories(array $categories)
    {
        $categoryPath = implode('_', $categories);
        $this->searchNavigationRequest->setSelected('cat', $categoryPath);
    }

    public function addManufactures(array $manufactures)
    {
        $manufacturePath = implode('_', $manufactures);
        $this->searchNavigationRequest->setSelected('vendor', $manufacturePath);
    }
}
