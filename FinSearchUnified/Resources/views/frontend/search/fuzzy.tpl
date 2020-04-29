{extends file='parent:frontend/search/fuzzy.tpl'}

{block name="frontend_search_headline"}
    <h1 class="search--headline">
        {if $snippetType == 'query'}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/query'}
                Search results for "{$finQueryInfoMessage.query}" (<strong>{$sSearchResults.sArticlesCount}</strong> hits)
            {/s}
        {elseif $snippetType == 'cat' || $snippetType == 'vendor'}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/filter'}
                Search results for {$finQueryInfoMessage.filter_name}
                <strong>"{$finQueryInfoMessage.filter_value}"</strong>
                (<strong>{$sSearchResults.sArticlesCount}</strong> hits)
            {/s}
        {else}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/default'}
                Search results (<strong>{$sSearchResults.sArticlesCount}</strong> hits)
            {/s}
        {/if}
    </h1>
    {if $finSmartDidYouMean.type && $finSmartDidYouMean.type !== 'forced'}
        <p id="fl-smart-did-you-mean" class="search--headline">
            {if $finSmartDidYouMean.type == 'did-you-mean'}
                {s name='frontend/search/fuzzy/did_you_mean_query'}
                    Did you mean "
                    <a href="{$finSmartDidYouMean.link}">{$finSmartDidYouMean.alternative_query}</a>
                    "?
                {/s}
            {elseif $finSmartDidYouMean.type == 'improved'}
                {s name='frontend/search/fuzzy/improved_query'}
                    Showing results for "{$finSmartDidYouMean.alternative_query}". Search instead for "
                    <a href="{$finSmartDidYouMean.link}">{$finSmartDidYouMean.original_query}</a>
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
