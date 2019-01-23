{extends file='parent:frontend/search/fuzzy.tpl'}

{block name="frontend_search_headline"}
    {$smarty.block.parent}

    {if $finSmartDidYouMean}
        {if $finSmartDidYouMean.type == 'did-you-mean'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/did_you_mean_query'}{/s}
            </p>
        {/if}
        {if $finSmartDidYouMean.type == 'improved'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/improved_query'}{/s}
            </p>
        {/if}
        {if $finSmartDidYouMean.type == 'corrected'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/corrected_query'}{/s}
            </p>
        {/if}
    {/if}
{/block}