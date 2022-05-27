{extends file="parent:frontend/listing/filter/facet-value-list.tpl"}

{block name="frontend_listing_filter_facet_value_list"}
    {$type = 'value-list-single'}
    {if $facet->getAttribute('multiselect')}
        {$type = 'value-list'}
    {/if}

    {include file='frontend/listing/filter/_includes/filter-multi-selection.tpl' filterType=$type}
{/block}