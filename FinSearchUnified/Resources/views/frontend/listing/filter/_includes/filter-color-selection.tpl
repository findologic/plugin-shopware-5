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
    <label class="filter-panel--label {if $option->isActive()|escape:'htmlall'}active{/if}"
           for="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}">

        {$colorCode = $option->getColorcode()}
        {$imageUrl = $option->getImageUrl()}

        {if $colorCode && $imageUrl}
            <div class="color--filter-item" style="background-image: url({$imageUrl}); background-color: {$colorCode};" title="{$option->getId()|escape:'htmlall'}"></div>
        {elseif $imageUrl}
            <div class="color--filter-item" style="background-image: url({$imageUrl}), url('/custom/plugins/FinSearchUnified/Resources/views/frontend/_public/src/img/no-picture.png');" title="{$option->getId()|escape:'htmlall'}"></div>
        {elseif $colorCode}
            <div class="color--filter-item" style="background: {$colorCode};" title="{$option->getId()|escape:'htmlall'}"></div>
        {else}
            <img class="filter-panel--media-image color--filter-item" title="{$option->getId()|escape:'htmlall'}" src="/custom/plugins/FinSearchUnified/Resources/views/frontend/_public/src/img/no-picture.png" alt="{$option->getId()|escape:'htmlall'}">
        {/if}

        <span class="color--filter-name">{$option->getId()|escape:'htmlall'}</span>
    </label>
{/block}
