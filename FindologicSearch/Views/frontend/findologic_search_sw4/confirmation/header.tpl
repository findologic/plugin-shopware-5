{block name="frontend_index_header_javascript" append}
<script type="text/javascript">
    {foreach from=$productsinorder item=c}
    _paq.push(['addEcommerceItem',
        "{$c.productordernumber}",
        "{$c.productname|addslashes}",
        [{foreach from=$c.productcategories item=p name=cat}"{$p}"{if $smarty.foreach.cat.last}  {else}, {/if}{/foreach}], 
        {$c.productprice},
        {$c.productquantity}
    ]);
 {/foreach}
    _paq.push(['trackEcommerceOrder',
        "{$sOrderNumber}",
        {$ORDER_TOTAL},
        {$ORDER_SUB_TOTAL},
        {$ORDER_TAX_AMOUNT},
        {$ORDER_SHIPPING_AMOUNT},
        {$ORDER_DISCOUNT}
    ]);
   _paq.push(['trackPageView']);
</script>
{/block}

