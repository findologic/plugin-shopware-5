{extends file="parent:frontend/listing/filter/_includes/filter-multi-selection.tpl"}

{* Extension of this wrapper is needed, to not conflict with filter-color-selection.tpl *}
{block name="frontend_listing_filter_facet_multi_selection_option_container"}
    <div class="option--container">
        {block name="frontend_listing_filter_facet_multi_selection_input"}
            {$disabled = $facet->isActive() && $filterType == 'value-list-single' && !$option->isActive()}

            <span class="filter-panel--input filter-panel--{$inputType} {if $disabled} is--disabled{/if}">
                {$name = "__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"}
                {if $filterType == 'radio'}
                    {$name = {$facet->getFieldName()|escape:'htmlall'} }
                {/if}

                <input type="{$inputType}"
                       id="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"
                       name="{$name}"
                       value="{$option->getId()|escape:'htmlall'}"
                       {if $option->isActive()}checked="checked" {/if}
                       {if $disabled}disabled="disabled" {/if}
                />

                <span class="input--state {$inputType}--state">&nbsp;</span>
            </span>
        {/block}

        {block name="frontend_listing_filter_facet_multi_selection_label"}
            {$smarty.block.parent}
        {/block}
    </div>
{/block}
