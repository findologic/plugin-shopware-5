{block name="frontend_index_header_javascript_modernizr_lib" prepend}
<script type="text/javascript">
    fl_paq.push(['setEcommerceView',
        "{if !empty($PRODUCT_ORDERNUMBER)}{$PRODUCT_ORDERNUMBER}{/if}",
        "{if !empty($PRODUCT_TITLE)}{$PRODUCT_TITLE|addslashes}{/if}",
        {if !empty($PRODUCT_CATEGORY)}[{foreach from=$PRODUCT_CATEGORY item=c name=cat}"{$c}"{if not $smarty.foreach.cat.last}, {/if}{/foreach}], {/if}
        {$PRODUCT_PRICE}
    ]);
    fl_paq.push(['trackPageView']);
</script>
{/block}  

