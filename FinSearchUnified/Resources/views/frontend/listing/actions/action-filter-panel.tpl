{extends file="parent:frontend/listing/actions/action-filter-panel.tpl"}

{block name="frontend_listing_actions_filter_form_page"}
    {if $finPushAttribs}
        {foreach $finPushAttribs as $finFilterName => $finFilterValues}
            {foreach $finFilterValues as $finFilterValue => $finWeight}
                <input type="hidden" name="pushAttrib[{$finFilterName}][{$finFilterValue}]" value="{$finWeight}"/>
            {/foreach}
        {/foreach}
    {/if}

    {$smarty.block.parent}
{/block}