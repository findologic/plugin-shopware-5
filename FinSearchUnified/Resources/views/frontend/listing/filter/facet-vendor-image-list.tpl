{extends file="parent:frontend/listing/filter/facet-media-list.tpl"}

{block name="frontend_listing_filter_facet_media_list"}
    {$type = 'value-list-single'}
    {if $facet->getAttribute('multiselect')}
        {$type = 'value-list'}
    {/if}

    {include file='frontend/listing/filter/_includes/filter-multi-media-selection.tpl' filterType=$type}
{/block}