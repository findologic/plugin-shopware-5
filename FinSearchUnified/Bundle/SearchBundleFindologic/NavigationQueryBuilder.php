<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

class NavigationQueryBuilder extends QueryBuilder
{
    const ENDPOINT = 'selector.php';

    public function addCategories(array $categories)
    {
        $this->parameters['selected'] = ['cat' => $categories];
    }
}
