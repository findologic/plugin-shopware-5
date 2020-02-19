{extends file='parent:frontend/search/fuzzy.tpl'}

{block name="frontend_search_headline"}
    <h1 class="search--headline">
        {if $snippetType == 'query'}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/query'}
                'Search results for {$finQueryInfoMessage.query} (
                <strong>{$sSearchResults.sArticlesCount}</strong>
                hits)'
            {/s}
        {elseif $snippetType == 'cat'}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/cat'}
                'Search results for {$finQueryInfoMessage.filter_name}
                <strong>{$finQueryInfoMessage.cat}</strong>
                (
                <strong>{$sSearchResults.sArticlesCount}</strong>
                hits)'
            {/s}
        {elseif $snippetType == 'vendor'}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/vendor'}
                'Search results for {$finQueryInfoMessage.filter_name}
                <strong>{$finQueryInfoMessage.vendor}</strong>
                (
                <strong>{$sSearchResults.sArticlesCount}</strong>
                hits)'
            {/s}
        {else}
            {s namespace='frontend/search/query_info_message' name='frontend/search/query_info_message/default'}
                'Search results (
                <strong>{$sSearchResults.sArticlesCount}</strong>
                hits)'
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
                    <a href="{url controller='search'}?sSearch={$finSmartDidYouMean.original_query}&forceOriginalQuery=1">{$finSmartDidYouMean.original_query}</a>
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
