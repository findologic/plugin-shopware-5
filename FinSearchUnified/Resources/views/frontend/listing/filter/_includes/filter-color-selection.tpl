{extends file="parent:frontend/listing/filter/_includes/filter-multi-selection.tpl"}

{block name="frontend_listing_filter_facet_multi_selection_input"}
    <span class="filter-panel--input filter-panel--{$inputType}" style="display: none">
        {$name = "__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"}
        {if $filterType == 'radio'}
            {$name = {$facet->getFieldName()|escape:'htmlall'} }
        {/if}

        <input type="{$inputType}"
               id="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"
               name="{$name}"
               value="{$option->getId()|escape:'htmlall'}" style="background-color: #fff !important; content: ''"
               {if $option->isActive()}checked="checked" {/if}/>

        <span class="input--state {$inputType}--state">&nbsp;</span>
    </span>
{/block}

{block name="frontend_listing_filter_facet_multi_selection_label"}
    <label class="filter-panel--label"
           for="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}">

        {if $option->getColorcode()}
            {$mediaFile = $option->getColorcode()}
            <div style="width:30px; height: 30px; margin: 2px; border: 1px solid #dbd7e5; background: {$mediaFile};" title="{$option->getId()|escape:'htmlall'}"></div>
        {else}
            <img style="width:30px; height: 30px; border: 1px solid #dbd7e5; margin: 2px;" class="filter-panel--media-image" title="{$option->getId()|escape:'htmlall'}" src="/custom/plugins/FinSearchUnified/Resources/views/frontend/_public/src/img/no-picture.png" alt="{$option->getId()|escape:'htmlall'}">
        {/if}
    </label>
{/block}
