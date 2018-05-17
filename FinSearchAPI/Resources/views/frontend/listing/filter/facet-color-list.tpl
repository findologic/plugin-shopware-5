{extends file="parent:frontend/listing/filter/facet-media-list.tpl"}

{block name="frontend_listing_filter_facet_media_list"}
    {$type = 'value-list'}
    {$listingMode = {config name="listingMode"}}
    {if $listingMode == 'filter_ajax_reload'}
        {$type = 'value-list-single'}
    {/if}

    {include file='frontend/listing/filter/_includes/filter-color-selection.tpl' filterType=$type}
{/block}