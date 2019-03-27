{extends file='parent:frontend/search/fuzzy.tpl'}

{block name="frontend_search_headline"}
    <h1 class="search--headline">
        {if $finSmartDidYouMean == false || $finSmartDidYouMean.type == 'did-you-mean'}
            {s name='SearchHeadline'}{/s}
        {else}
            {s name='frontend/search/fuzzy/search_head_line'}
                The following products have been found matching your search "{$finSmartDidYouMean.alternative_query}": <span class="headline--product-count">{$sSearchResults.sArticlesCount}</span>
            {/s}
        {/if}
    </h1>

    {if $finSmartDidYouMean}
        <p id="fl-smart-did-you-mean" class="search--headline">
            {if $finSmartDidYouMean.type == 'did-you-mean'}
                {s name='frontend/search/fuzzy/did_you_mean_query'}
                    Did you mean "
                    <a href="{url controller='search'}?sSearch={$finSmartDidYouMean.alternative_query}&forceOriginalQuery=1">{$finSmartDidYouMean.alternative_query}</a>
                    "?
                {/s}
            {elseif $finSmartDidYouMean.type == 'improved'}
                {s name='frontend/search/fuzzy/improved_query'}
                    Showing results for "{$finSmartDidYouMean.alternative_query}". Search instead for "
                    <a href="{urlcontroller='search'}?sSearch={$finSmartDidYouMean.original_query}&forceOriginalQuery=1">{$finSmartDidYouMean.original_query}</a>
                    "?
                {/s}
            {elseif $finSmartDidYouMean.type == 'corrected'}
                {s name='frontend/search/fuzzy/corrected_query'}
                    No results for "{$finSmartDidYouMean.original_query}". Showing results for "{$finSmartDidYouMean.alternative_query}" instead
                {/s}
            {else}
                {* Nothing to render here *}
            {/if}
        </p>
    {/if}
{/block}