{extends file='parent:frontend/search/fuzzy.tpl'}

{block name="frontend_search_headline"}
    {$smarty.block.parent}

    {if $finSmartDidYouMean}
        {if $finSmartDidYouMean.type == 'did-you-mean'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/did_you_mean_query'}
                    Did you mean "
                    <a href="{url controller='search'}?sSearch={$finSmartDidYouMean.alternative_query}&forceOriginalQuery=1">{$finSmartDidYouMean.alternative_query}</a>
                    "?
                {/s}
            </p>
        {/if}
        {if $finSmartDidYouMean.type == 'improved'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/improved_query'}
                    Showing results for "{$finSmartDidYouMean.alternative_query}". Search instead for "
                    <a href="{urlcontroller='search'}?sSearch={$finSmartDidYouMean.original_query}&forceOriginalQuery=1">{$finSmartDidYouMean.original_query}</a>
                    "?
                {/s}
            </p>
        {/if}
        {if $finSmartDidYouMean.type == 'corrected'}
            <p id="fl-smart-did-you-mean">
                {s name='frontend/search/fuzzy/corrected_query'}
                    No results for "{$finSmartDidYouMean.original_query}". Showing results for "{$finSmartDidYouMean.alternative_query}" instead
                {/s}
            </p>
        {/if}
    {/if}
{/block}