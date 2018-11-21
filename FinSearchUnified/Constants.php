<?php

namespace FinSearchUnified;

class Constants
{
    /**
     * Only a-zA-Z0-9_] are permitted for cache ids.
     */
    const CACHE_ID_PRODUCT_STREAMS = 'fin_product_streams';

    /**
     * Give the entries a lifetime of 660 seconds (11 minutes).
     * Since we only support a maximum runtime of 10 minutes per export page, this will automatically clear the cache
     * once the export is finished, aborted or triggered manually.
     */
    const CACHE_LIFETIME_PRODUCT_STREAMS = 660;

    const INTEGRATION_TYPE_DI = 'Direct Integration';
    const INTEGRATION_TYPE_API = 'API';
}
