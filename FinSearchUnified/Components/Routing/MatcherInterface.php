<?php

namespace FinSearchUnified\Components\Routing;

use Shopware\Components\Routing\Context;

interface MatcherInterface
{
    /**
     * @param string $pathInfo
     *
     * @return string|array|false
     */
    public function match($pathInfo, Context $context);
}
