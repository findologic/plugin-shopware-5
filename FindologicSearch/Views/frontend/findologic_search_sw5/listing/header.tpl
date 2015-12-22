{block name="frontend_index_header_javascript_modernizr_lib" append}
    {if !empty($CATEGORY_PATH)}
        <script type="text/javascript">
            fl_paq.push(['setEcommerceView',
                false,
                false,
                [{foreach from=$CATEGORY_PATH item=c name=cat}"{$c}"{if not $smarty.foreach.cat.last}, {/if}{/foreach}]
            ]);
            fl_paq.push(['trackPageView']);
        </script>
    {/if}
{/block}
