{block name="frontend_index_header_javascript_modernizr_lib" prepend}
<script type="text/javascript">
    {foreach from=$productsinorder item=c}
    fl_paq.push(['addEcommerceItem',
        "{$c.productordernumber}",
        "{$c.productname|addslashes}",
        [{foreach from=$c.productcategories item=p name=cat}"{$p}"{if not $smarty.foreach.cat.last}, {/if}{/foreach}],
        {$c.productprice|replace:',':'.'},
        {$c.productquantity}
    ]);
 {/foreach}
    fl_paq.push(['trackEcommerceOrder',
        "{$sOrderNumber}",
        {$ORDER_TOTAL},
        {$ORDER_SUB_TOTAL},
        {$ORDER_TAX_AMOUNT},
        {$ORDER_SHIPPING_AMOUNT},
        {$ORDER_DISCOUNT}
    ]);
    fl_paq.push(['trackPageView']);
</script>
{/block}

